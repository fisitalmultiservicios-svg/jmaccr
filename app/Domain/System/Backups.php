<?php

namespace App\Domain\System;

use App\Domain\Auth\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

final class Backups
{
    public const TABLES = ['roles', 'permissions', 'users', 'role_permissions', 'user_roles', 'media', 'site_settings', 'site_themes', 'pages', 'page_revisions', 'nav_menus', 'nav_items', 'menu_revisions', 'cms_sections', 'categories', 'brands', 'products', 'category_product', 'product_media', 'product_attributes', 'product_documents', 'sequences', 'preorders', 'preorder_items', 'preorder_events', 'contact_submissions', 'contact_events', 'posts', 'post_revisions', 'post_categories', 'post_category_links', 'notifications_outbox', 'redirects', 'audit_logs', 'import_batches', 'import_errors'];

    private function directory(): string
    {
        $p = storage_path('app/private/backups');
        if (! is_dir($p)) {
            mkdir($p, 0700, true);
        }

        return $p;
    }

    public function keyPath(): string
    {
        return $this->directory().'/recovery.key';
    }

    private function key(): string
    {
        $p = $this->keyPath();
        if (! is_file($p)) {
            $f = fopen($p, 'x');
            if (! $f) {
                throw new \RuntimeException('No se pudo crear clave privada.');
            }chmod($p, 0600);
            fwrite($f, base64_encode(random_bytes(32)));
            fclose($f);
        }$k = base64_decode(trim(file_get_contents($p)), true);
        if (strlen($k ?: '') !== 32) {
            throw new \RuntimeException('Clave de recuperación inválida.');
        }

        return $k;
    }

    public function inventory(): array
    {
        $out = [];
        foreach (glob($this->directory().'/*.jmb') as $p) {
            $out[] = ['name' => basename($p), 'bytes' => filesize($p), 'time' => filemtime($p), 'sha256' => hash_file('sha256', $p)];
        }usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    public function path(string $name): string
    {
        abort_unless(preg_match('/^jmac-[0-9]{14}-[a-f0-9-]{36}\.jmb$/', $name), 404);
        $p = $this->directory().'/'.$name;
        abort_unless(is_file($p) && ! is_link($p), 404);

        return $p;
    }

    private function schema(): array
    {
        $s = [];
        foreach (self::TABLES as $t) {
            $columns = Schema::getColumnListing($t);
            sort($columns);
            $s[$t] = $columns;
        }

        return $s;
    }

    private function mediaPath(string $p): bool
    {
        return (bool) preg_match('~^media/[a-f0-9-]+(?:-thumb)?\.(webp|pdf)$~', $p);
    }

    public function create(bool $allowMissingMedia = false): string
    {
        $this->key();
        $work = $this->directory().'/tmp-'.Str::uuid();
        mkdir($work, 0700);
        $zipPath = $work.'/data.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL);
        $metadata = ['version' => 1, 'schema' => $this->schema(), 'app_key' => config('app.key'), 'created_at' => now()->toIso8601String(), 'files' => [], 'counts' => [], 'missing_media' => []];
        $bytes = 0;
        try {
            DB::transaction(function () use (&$metadata, &$bytes, $work, $zip, $allowMissingMedia) {
                foreach (self::TABLES as $table) {
                    $count = DB::table($table)->count();
                    if ($count > 50000) {
                        throw new \RuntimeException('Superado el límite de 50.000 filas por tabla; usa copia del hosting.');
                    }$p = $work.'/'.$table.'.ndjson';
                    $f = fopen($p, 'x');
                    chmod($p, 0600);
                    foreach (DB::table($table)->cursor() as $row) {
                        $line = json_encode($row, JSON_THROW_ON_ERROR)."\n";
                        fwrite($f, $line);
                        $bytes += strlen($line);
                        if ($bytes > 240 * 1024 * 1024) {
                            throw new \RuntimeException('Copia supera 240 MB; usa backup privado del hosting.');
                        }
                    }fclose($f);
                    $name = 'tables/'.$table.'.ndjson';
                    $metadata['files'][$name] = hash_file('sha256', $p);
                    $metadata['counts'][$table] = $count;
                    $zip->addFile($p, $name);
                }foreach (DB::table('media')->get() as $media) {
                    $paths = [$media->path];
                    foreach (json_decode($media->variants_json ?? '{}', true) as $v) {
                        $paths[] = $v['path'];
                    }foreach (array_unique($paths) as $p) {
                        if ($allowMissingMedia && $this->mediaPath($p) && $media->disk === 'local' && ! Storage::disk('local')->exists($p)) {
                            $metadata['missing_media'][] = $p;

                            continue;
                        }
                        if (! $this->mediaPath($p) || $media->disk !== 'local' || ! Storage::disk('local')->exists($p)) {
                            throw new \RuntimeException('Falta un medio privado; no se generó una copia parcial.');
                        }$file = Storage::disk('local')->path($p);
                        $bytes += filesize($file);
                        if ($bytes > 240 * 1024 * 1024) {
                            throw new \RuntimeException('Copia supera 240 MB.');
                        }$name = 'files/'.$p;
                        $metadata['files'][$name] = hash_file('sha256', $file);
                        $zip->addFile($file, $name);
                    }
                }
            });
            $zip->addFromString('manifest.json', json_encode($metadata, JSON_THROW_ON_ERROR));
            $zip->close();
            $name = 'jmac-'.now()->format('YmdHis').'-'.Str::uuid().'.jmb';
            $this->transform($zipPath, $this->directory().'/'.$name, true);
            Audit::record('backups.created', 'backups', null, ['count' => array_sum($metadata['counts'])]);

            return $name;
        } finally {
            try {
                $zip->close();
            } catch (\Throwable $ignored) {
            }foreach (glob($work.'/*') as $p) {
                @unlink($p);
            }@rmdir($work);
        }
    }

    private function transform(string $source, string $destination, bool $encrypt): void
    {
        $key = $this->key();
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'xb');
        if (! $out) {
            throw new \RuntimeException('Destino ocupado.');
        }chmod($destination, 0600);
        $header = "JMACB1\n";
        $index = 0;
        $complete = false;
        try {
            if ($encrypt) {
                fwrite($out, $header);
                while (! feof($in)) {
                    $plain = fread($in, 1024 * 1024);
                    if ($plain === '') {
                        break;
                    }$iv = random_bytes(12);
                    $tag = '';
                    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $header.pack('N', $index));
                    if ($cipher === false) {
                        throw new \RuntimeException('No se pudo cifrar.');
                    }fwrite($out, pack('N', strlen($cipher)).$iv.$tag.$cipher);
                    $index++;
                }$iv = random_bytes(12);
                $tag = '';
                openssl_encrypt('', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $header.pack('N', $index));
                fwrite($out, pack('N', 0).$iv.$tag);
            } else {
                if (fread($in, strlen($header)) !== $header) {
                    throw new \RuntimeException('Formato de copia inválido.');
                }while (true) {
                    $length = fread($in, 4);
                    if (strlen($length) !== 4) {
                        throw new \RuntimeException('Copia truncada.');
                    }$size = unpack('N', $length)[1];
                    if ($size > 1024 * 1024) {
                        throw new \RuntimeException('Bloque demasiado grande.');
                    }$iv = fread($in, 12);
                    $tag = fread($in, 16);
                    $cipher = $size ? fread($in, $size) : '';
                    if (strlen($iv) !== 12 || strlen($tag) !== 16 || strlen($cipher) !== $size) {
                        throw new \RuntimeException('Copia truncada.');
                    }$plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $header.pack('N', $index));
                    if ($plain === false) {
                        throw new \RuntimeException('Clave incorrecta o copia alterada.');
                    }if ($size === 0) {
                        if (fread($in, 1) !== '') {
                            throw new \RuntimeException('Contenido extra en copia.');
                        }break;
                    }fwrite($out, $plain);
                    $index++;
                    if (ftell($out) > 256 * 1024 * 1024) {
                        throw new \RuntimeException('Copia demasiado grande.');
                    }
                }
            }$complete = true;
        } finally {
            fclose($in);
            fclose($out);
            if (! $complete) {
                @unlink($destination);
            }
        }
    }

    public function restore(string $name): void
    {
        abort_if(app()->isProduction(), 403, 'Restauración web/CLI bloqueada en producción. Valida y promueve una restauración desde staging con autorización.');
        $source = $this->path($name);
        $work = $this->directory().'/tmp-'.Str::uuid();
        mkdir($work, 0700);
        $zipFile = $work.'/restore.zip';
        $zip = new ZipArchive;
        $recovery = null;
        try {
            $this->transform($source, $zipFile, false);
            if ($zip->open($zipFile) !== true) {
                throw new \RuntimeException('Archivo de recuperación inválido.');
            }$manifest = $zip->getFromName('manifest.json');
            if (! $manifest || strlen($manifest) > 1000000) {
                throw new \RuntimeException('Manifiesto inválido.');
            }$m = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
            if (($m['version'] ?? 0) !== 1 || $m['schema'] !== $this->schema() || ! preg_match('/^base64:[A-Za-z0-9+\/=]{44}$/', $m['app_key'] ?? '')) {
                throw new \RuntimeException('Copia incompatible con el esquema o clave de app.');
            }if ($zip->numFiles !== count($m['files']) + 1) {
                throw new \RuntimeException('Hay archivos no declarados.');
            }$total = 0;
            $tables = [];
            $media = [];
            foreach ($m['files'] as $entry => $checksum) {
                $isTable = preg_match('~^tables/([a-z_]+)\.ndjson$~', $entry, $match) && in_array($match[1], self::TABLES);
                $isMedia = str_starts_with($entry, 'files/') && $this->mediaPath(substr($entry, 6));
                if (! $isTable && ! $isMedia) {
                    throw new \RuntimeException('Ruta no permitida.');
                }$stat = $zip->statName($entry);
                if (! $stat || ($total += (int) $stat['size']) > 256 * 1024 * 1024) {
                    throw new \RuntimeException('Tamaño inválido.');
                }$content = $zip->getFromName($entry);
                if ($content === false || ! hash_equals($checksum, hash('sha256', $content))) {
                    throw new \RuntimeException('Checksum inválido.');
                }if ($isTable) {
                    $rows = [];
                    foreach (explode("\n", trim($content)) as $line) {
                        if ($line === '') {
                            continue;
                        }$row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        $keys = array_keys($row);
                        sort($keys);
                        if ($keys !== $m['schema'][$match[1]]) {
                            throw new \RuntimeException('Columnas incompatibles.');
                        }$rows[] = $row;
                    }if (count($rows) !== $m['counts'][$match[1]]) {
                        throw new \RuntimeException('Conteo inconsistente.');
                    }$tables[$match[1]] = $rows;
                } else {
                    $p = substr($entry, 6);
                    $media[$p] = $content;
                }
            }if (count($tables) !== count(self::TABLES)) {
                throw new \RuntimeException('Faltan tablas.');
            }if (config('app.key') !== $m['app_key'] && (! is_file(base_path('.env')) || ! is_writable(base_path('.env')))) {
                throw new \RuntimeException('Archivo privado .env no escribible; no se restauró.');
            }$recovery = $this->create(true);
            foreach ($media as $path => $content) {
                if (! Storage::disk('local')->put($path, $content)) {
                    throw new \RuntimeException('No se pudo restaurar un medio.');
                }
            }$oldActor = auth()->id();
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($tables) {
                    foreach (array_reverse(self::TABLES) as $t) {
                        DB::table($t)->delete();
                    }foreach (self::TABLES as $t) {
                        foreach (array_chunk($tables[$t], 100) as $chunk) {
                            DB::table($t)->insert($chunk);
                        }
                    }foreach (['sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches'] as $t) {
                        DB::table($t)->delete();
                    }DB::table('users')->increment('auth_version');
                    DB::table('notifications_outbox')->whereIn('status', ['processing', 'pending', 'failed'])->update(['status' => 'cancelled', 'payload_json' => '', 'recipient' => null, 'claim_token' => null, 'locked_at' => null]);
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }if (config('app.key') !== $m['app_key']) {
                $env = base_path('.env');
                abort_unless(is_file($env) && is_writable($env), 500, 'Configura APP_KEY desde la copia privada; no generes otra clave.');
                $text = file_get_contents($env);
                $text = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$m['app_key'], $text, 1, $count);
                if (! $count) {
                    $text .= "\nAPP_KEY=".$m['app_key']."\n";
                }file_put_contents($env.'.restore', $text, LOCK_EX);
                chmod($env.'.restore', 0600);
                rename($env.'.restore', $env);
                Artisan::call('config:clear');
            }if ($oldActor && ! DB::table('users')->where('id', $oldActor)->exists()) {
                auth()->logout();
            }Audit::record('backups.restored', 'backups');
        } catch (\Throwable $e) {
            throw new \RuntimeException('Restauración no completada. '.($recovery ? 'Copia previa privada: '.$recovery.'. ' : '').$e->getMessage(), 0, $e);
        } finally {
            try {
                $zip->close();
            } catch (\Throwable $ignored) {
            }@unlink($zipFile);
            @rmdir($work);
        }
    }

    public function import(string $file): string
    {
        if (filesize($file) > 256 * 1024 * 1024 || file_get_contents($file, false, null, 0, 7) !== "JMACB1\n") {
            throw new \RuntimeException('Copia cifrada inválida.');
        }$name = 'jmac-'.now()->format('YmdHis').'-'.Str::uuid().'.jmb';
        if (! copy($file, $this->directory().'/'.$name)) {
            throw new \RuntimeException('No se pudo importar.');
        }chmod($this->directory().'/'.$name, 0600);
        Audit::record('backups.imported', 'backups');

        return $name;
    }
}
