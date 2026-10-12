<?php

namespace App\Domain\Catalog;

use App\Domain\Auth\Audit;
use App\Domain\Cms\Blocks;
use App\Domain\System\Backups;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CatalogImports
{
    private const TABLES = ['products' => ['id'], 'categories' => ['id'], 'brands' => ['id'], 'category_product' => ['category_id', 'product_id'], 'product_media' => ['id'], 'product_documents' => ['id'], 'product_attributes' => ['id']];

    public static function allowed(User $user): bool
    {
        return $user->hasPermission('products.create') && $user->hasPermission('products.update');
    }

    public static function canReplace(User $user): bool
    {
        return self::allowed($user) && $user->hasPermission('products.delete') && $user->hasPermission('categories.update') && $user->hasPermission('brands.update');
    }

    private function authorize(User $user, string $mode): void
    {
        abort_unless(self::allowed($user) && ($mode !== 'replace' || self::canReplace($user)), 403);
    }

    private function fingerprint(bool $lock = false): string
    {
        $hash = hash_init('sha256');
        foreach (self::TABLES as $table => $order) {
            $query = DB::table($table);
            foreach ($order as $column) {
                $query->orderBy($column);
            }
            if ($lock) {
                $query->lockForUpdate();
            }
            $count = 0;
            hash_update($hash, $table);
            foreach ($query->cursor() as $record) {
                if (++$count > 50000) {
                    throw ValidationException::withMessages(['file' => 'El catálogo supera el límite de importación de este hosting.']);
                }
                hash_update($hash, json_encode($record, JSON_THROW_ON_ERROR));
            }
        }

        return hash_final($hash);
    }

    private function products(): array
    {
        $products = [];
        foreach (DB::table('products')->whereNotNull('sku')->get() as $product) {
            $key = mb_strtoupper(trim($product->sku));
            if (isset($products[$key])) {
                throw ValidationException::withMessages(['file' => 'Hay SKU equivalentes repetidos en el catálogo. Corrígelos antes de importar.']);
            }
            $products[$key] = $product;
        }

        return $products;
    }

    private function checkRows(array $rows, User $user): void
    {
        $existing = $this->products();
        foreach ($rows as $row) {
            $fields = $row['fields'];
            $product = $existing[$fields['sku']] ?? null;
            $status = $fields['status'] ?? $product?->status ?? 'draft';
            abort_unless($status !== 'published' || $user->hasPermission('products.publish'), 403);
            foreach (['category' => 'categories', 'brand' => 'brands'] as $field => $table) {
                $record = null;
                if (! empty($fields[$field])) {
                    $record = DB::table($table)->where('slug', $this->slug($fields[$field], $table))->first();
                    abort_unless($user->hasPermission($table.($record ? '.update' : '.create')), 403);
                } elseif (! array_key_exists($field, $fields) && $product) {
                    $reference = $field === 'category' ? $product->category_primary_id : $product->brand_id;
                    $record = $reference ? DB::table($table)->find($reference) : null;
                    if ($status === 'published' && $record && $record->status !== 'published') {
                        throw ValidationException::withMessages(['file' => 'La fila '.$row['line'].' requiere publicar primero su categoría o marca, o incluirla expresamente en la hoja.']);
                    }
                }
                $image = $table === 'categories' ? 'cover_media_id' : 'logo_media_id';
                if ($status === 'published' && $record && $record->$image && ! DB::table('media')->where('id', $record->$image)->where('status', 'approved')->where('mime', 'like', 'image/%')->exists()) {
                    throw ValidationException::withMessages(['file' => 'La categoría o marca de la fila '.$row['line'].' tiene una imagen pendiente de aprobación.']);
                }
            }
            if ($status === 'published' && $product) {
                foreach (['product_media', 'product_documents'] as $table) {
                    $unapproved = DB::table($table)->join('media', 'media.id', '=', $table.'.media_id')->where('product_id', $product->id)->where('media.status', '!=', 'approved')->exists();
                    if ($unapproved) {
                        throw ValidationException::withMessages(['file' => 'El SKU de la fila '.$row['line'].' tiene medios pendientes de aprobación. Usa estado borrador o aprueba los medios primero.']);
                    }
                }
            }
        }
    }

    private function slug(string $name, string $prefix): string
    {
        return mb_substr(Str::slug($name) ?: $prefix.'-'.substr(hash('sha256', $name), 0, 12), 0, 165);
    }

    public function cleanup(): void
    {
        foreach (DB::table('import_batches')->where('status', 'ready')->where('created_at', '<', now()->subHours(2))->limit(30)->get() as $batch) {
            if (DB::table('import_batches')->where('id', $batch->id)->where('status', 'ready')->update(['status' => 'expired', 'updated_at' => now()])) {
                $mapping = json_decode($batch->mapping, true);
                $this->removePayload($mapping);
            }
        }
    }

    private function payloadPath(array $mapping): string
    {
        $token = $mapping['token'] ?? '';
        abort_unless(is_string($token) && preg_match('/^[a-f0-9-]{36}$/D', $token), 404);

        return 'catalog-imports/'.$token.'.jci';
    }

    private function removePayload(array $mapping): void
    {
        if (isset($mapping['token'])) {
            Storage::disk('local')->delete($this->payloadPath($mapping));
        }
    }

    public function rows(object $batch): array
    {
        $mapping = json_decode($batch->mapping, true, 32, JSON_THROW_ON_ERROR);
        $contents = Storage::disk('local')->get($this->payloadPath($mapping));
        try {
            $rows = json_decode(Crypt::decryptString($contents ?? ''), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($rows) || $rows === [] || count($rows) > ExcelCatalog::MAX_ROWS) {
                throw new \RuntimeException('Invalid payload');
            }

            return $rows;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'La vista previa ya no está disponible. Vuelve a cargar el Excel.']);
        }
    }

    public function preview(UploadedFile $file, string $mode, User $user): int
    {
        $this->authorize($user, $mode);
        $this->cleanup();
        abort_if(DB::table('import_batches')->where('actor_id', $user->id)->where('status', 'ready')->count() >= 10, 429, 'Cancela una vista previa pendiente antes de subir otra.');
        $mapping = ['version' => 1, 'mode' => $mode, 'token' => (string) Str::uuid(), 'source_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 120), 'source_sha256' => hash_file('sha256', $file->getRealPath())];
        $fingerprint = $this->fingerprint();
        try {
            $parsed = app(ExcelCatalog::class)->read($file->getRealPath());
        } catch (ValidationException $exception) {
            $parsed = ['rows' => [], 'errors' => [['row_number' => 0, 'safe_error' => implode(' ', $exception->validator->errors()->all())]]];
        }
        $rows = $parsed['rows'];
        $counts = ['new' => 0, 'updated' => 0, 'restored' => 0, 'retired' => 0, 'errors' => count($parsed['errors']), 'total' => count($rows)];
        $existing = $this->products();
        foreach ($rows as $row) {
            $product = $existing[$row['fields']['sku']] ?? null;
            $counts[$product ? ($product->deleted_at ? 'restored' : 'updated') : 'new']++;
        }
        if ($mode === 'replace') {
            $codes = array_column(array_column($rows, 'fields'), 'sku');
            $counts['retired'] = DB::table('products')->whereNull('deleted_at')->get()->filter(fn ($product) => ! in_array(mb_strtoupper(trim($product->sku ?? '')), $codes, true))->count();
        }
        $ready = $parsed['errors'] === [];
        if ($ready) {
            $this->checkRows($rows, $user);
            $mapping['fingerprint'] = $fingerprint;
            app(ExcelCatalog::class)->directory();
            if (! Storage::disk('local')->put($this->payloadPath($mapping), Crypt::encryptString(json_encode($rows, JSON_THROW_ON_ERROR)))) {
                throw ValidationException::withMessages(['file' => 'No se pudo guardar la vista previa privada.']);
            }
        }
        $id = DB::transaction(function () use ($mapping, $counts, $parsed, $ready, $user) {
            $id = DB::table('import_batches')->insertGetId(['actor_id' => $user->id, 'mapping' => json_encode($mapping, JSON_THROW_ON_ERROR), 'status' => $ready ? 'ready' : 'invalid', 'counts' => json_encode($counts, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($parsed['errors'] as $error) {
                DB::table('import_errors')->insert(array_replace($error, ['safe_error' => mb_substr($error['safe_error'], 0, 240), 'import_batch_id' => $id, 'created_at' => now(), 'updated_at' => now()]));
            }
            Audit::record('catalog.import_preview', 'import_batches', $id, ['status' => $ready ? 'ready' : 'invalid', 'count' => $counts['total']]);

            return $id;
        });

        return $id;
    }

    private function taxonomy(string $table, ?string $name, string $status): ?int
    {
        if (! $name) {
            return null;
        }
        $slug = $this->slug($name, $table);
        $record = DB::table($table)->where('slug', $slug)->first();
        if ($record) {
            if ($status === 'published' && $record->status !== 'published') {
                DB::table($table)->where('id', $record->id)->update(['status' => 'published', 'updated_at' => now()]);
            }

            return $record->id;
        }

        return DB::table($table)->insertGetId(['name' => $name, 'slug' => $slug, 'status' => $status === 'published' ? 'published' : 'hidden', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function apply(int $id, User $user): array
    {
        $directory = app(ExcelCatalog::class)->directory();
        $lock = fopen($directory.'/apply.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            throw ValidationException::withMessages(['file' => 'Otra importación está terminando. Espera y vuelve a confirmar.']);
        }
        chmod($directory.'/apply.lock', 0600);
        try {
            $batch = DB::table('import_batches')->where('actor_id', $user->id)->find($id);
            abort_unless($batch, 404);
            $mapping = json_decode($batch->mapping, true, 32, JSON_THROW_ON_ERROR);
            $this->authorize($user, $mapping['mode']);
            if ($batch->status === 'completed') {
                return json_decode($batch->counts, true);
            }
            if ($batch->status !== 'ready' || now()->diffInSeconds($batch->created_at, true) > 7200) {
                throw ValidationException::withMessages(['file' => 'Esta vista previa ya no puede aplicarse. Vuelve a subir el Excel.']);
            }
            $rows = $this->rows($batch);
            $this->checkRows($rows, $user);
            if (! hash_equals($mapping['fingerprint'], $this->fingerprint())) {
                throw ValidationException::withMessages(['file' => 'El catálogo cambió después del análisis. Vuelve a cargar el Excel para revisar los cambios actuales.']);
            }
            try {
                $mapping['backup'] = app(Backups::class)->create(true);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages(['file' => 'No se pudo crear la copia privada. El catálogo no se ha modificado. Revisa Backups y vuelve a intentar.']);
            }
            $counts = DB::transaction(function () use ($id, $user, $mapping, $rows) {
                $batch = DB::table('import_batches')->where('actor_id', $user->id)->lockForUpdate()->find($id);
                abort_unless($batch && $batch->status === 'ready', 409);
                if (! hash_equals($mapping['fingerprint'], $this->fingerprint(true))) {
                    throw ValidationException::withMessages(['file' => 'El catálogo cambió durante la confirmación. Analiza el Excel de nuevo.']);
                }
                $this->checkRows($rows, $user);
                $existing = $this->products();
                $slugs = array_fill_keys(DB::table('products')->pluck('slug')->all(), true);
                $nextSuffix = [];
                $ids = $categoryIds = $brandIds = [];
                foreach ($rows as $row) {
                    $fields = $row['fields'];
                    $product = $existing[$fields['sku']] ?? null;
                    $values = array_diff_key($fields, array_flip(['category', 'brand', 'tags_text']));
                    $values['status'] = $values['status'] ?? $product?->status ?? 'draft';
                    if (isset($values['description'])) {
                        $values['description'] = Blocks::richText($values['description']);
                    }
                    if (array_key_exists('category', $fields)) {
                        $values['category_primary_id'] = $this->taxonomy('categories', $fields['category'], $values['status']);
                    }
                    if (array_key_exists('brand', $fields)) {
                        $values['brand_id'] = $this->taxonomy('brands', $fields['brand'], $values['status']);
                    }
                    if (array_key_exists('tags_text', $fields)) {
                        $values['tags_json'] = json_encode(array_values(array_filter(array_map('trim', explode(',', $fields['tags_text'] ?? '')))));
                    }
                    $values['deleted_at'] = null;
                    $values['updated_at'] = now();
                    $values['search_text'] = Str::ascii(mb_strtolower(implode(' ', [$values['name'], $values['sku'], $values['summary'] ?? $product?->summary ?? '', strip_tags($values['description'] ?? $product?->description ?? ''), $fields['tags_text'] ?? $product?->tags_json ?? ''])));
                    if ($product) {
                        $productId = $product->id;
                        DB::table('products')->where('id', $productId)->update($values);
                    } else {
                        $slug = $this->slug($values['name'], 'producto');
                        $candidate = $slug;
                        $suffix = $nextSuffix[$slug] ?? 0;
                        while (isset($slugs[$candidate])) {
                            $candidate = $slug.'-'.(++$suffix);
                        }
                        $nextSuffix[$slug] = $suffix;
                        $slugs[$candidate] = true;
                        $productId = DB::table('products')->insertGetId($values + ['slug' => $candidate, 'unit' => 'unidad', 'created_at' => now()]);
                    }
                    if (array_key_exists('category', $fields)) {
                        DB::table('category_product')->where('product_id', $productId)->delete();
                        if ($values['category_primary_id']) {
                            DB::table('category_product')->insert(['product_id' => $productId, 'category_id' => $values['category_primary_id']]);
                        }
                    }
                    $ids[] = $productId;
                    $categoryIds[] = $values['category_primary_id'] ?? $product?->category_primary_id;
                    $brandIds[] = $values['brand_id'] ?? $product?->brand_id;
                }
                if ($mapping['mode'] === 'replace') {
                    $categoryIds = DB::table('category_product')->whereIn('product_id', $ids)->pluck('category_id')->all();
                    for ($depth = 0; $depth < 10; $depth++) {
                        $parents = DB::table('categories')->whereIn('id', $categoryIds)->whereNotNull('parent_id')->pluck('parent_id')->all();
                        $expanded = array_values(array_unique([...$categoryIds, ...$parents]));
                        if (count($expanded) === count($categoryIds)) {
                            break;
                        }
                        $categoryIds = $expanded;
                    }
                    DB::table('products')->whereNotIn('id', $ids)->whereNull('deleted_at')->update(['status' => 'archived', 'deleted_at' => now(), 'updated_at' => now()]);
                    DB::table('categories')->whereNotIn('id', array_filter($categoryIds))->update(['status' => 'hidden', 'updated_at' => now()]);
                    DB::table('brands')->whereNotIn('id', array_filter($brandIds))->update(['status' => 'hidden', 'updated_at' => now()]);
                }
                $counts = json_decode($batch->counts, true);
                DB::table('import_batches')->where('id', $id)->update(['status' => 'completed', 'mapping' => json_encode($mapping), 'updated_at' => now()]);
                Audit::record('catalog.import_applied', 'import_batches', $id, ['status' => 'completed', 'count' => count($rows)]);

                return $counts;
            }, 3);
            $this->removePayload($mapping);

            return $counts;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function cancel(int $id, User $user): void
    {
        $batch = DB::table('import_batches')->where('actor_id', $user->id)->find($id);
        abort_unless($batch && in_array($batch->status, ['ready', 'invalid', 'expired']), 404);
        $this->authorize($user, json_decode($batch->mapping, true)['mode']);
        if (DB::table('import_batches')->where('id', $id)->where('status', $batch->status)->update(['status' => 'cancelled', 'updated_at' => now()])) {
            $this->removePayload(json_decode($batch->mapping, true));
            Audit::record('catalog.import_cancelled', 'import_batches', $id, ['status' => 'cancelled']);
        }
    }
}
