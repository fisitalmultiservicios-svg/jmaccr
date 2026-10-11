"""Regression for cPanel unzip permissions, using the native extractor."""
from pathlib import Path
from zipfile import ZipFile
import hashlib
import json
import shutil
import stat
import subprocess
import tempfile

ROOT = Path('/workspace/jmaccr')
DELIVERY = Path('/workspace/asset-repair-delivery')
stage = Path(tempfile.mkdtemp(prefix='jmac-asset-permissions-', dir='/workspace'))
try:
    subprocess.run(['unzip', '-q', '/workspace/JMAC-cpanel.zip', '-d', str(stage)], check=True)
    (stage / 'archivos_publicos').rename(stage / 'public_html')
    app = stage / 'jmac_app'
    for name in ['config/auth.php', 'app/Http/Middleware/AdminAccess.php']:
        shutil.copyfile(ROOT / name, app / name)
    (app / '.env').write_text('# Private disposable QA fixture; use process configuration\n')
    (app / '.env').chmod(0o600)
    marker = app / 'storage/logs/permission-preservation.txt'
    marker.write_text('Private storage must not change\n')
    marker.chmod(0o600)
    (app / 'storage').chmod(0o700)
    app.chmod(0o700)
    protected = [app / '.env', marker, app / 'storage', app,
                 stage / 'public_html/index.php', app / 'public/index.php',
                 app / 'config/auth.php', app / 'app/Http/Middleware/AdminAccess.php']
    def fingerprint(path):
        return (stat.S_IMODE(path.stat().st_mode),
                hashlib.sha256(path.read_bytes()).hexdigest() if path.is_file() else None)
    before = {path: fingerprint(path) for path in protected}
    subprocess.run(['unzip', '-oq', '/workspace/visual-parts-delivery/JMAC-visual-PHP-CSS-logo.zip', '-d', str(stage)], check=True)
    old_css = stage / 'public_html/build/assets/app-O1-jpzL0.css'
    old_logo = stage / 'public_html/brand/jmac-logo.png'
    assert stat.S_IMODE(old_css.stat().st_mode) == 0o600
    assert stat.S_IMODE(old_logo.stat().st_mode) == 0o600
    assert not (old_css.stat().st_mode & stat.S_IROTH), 'The old archive reproduces the bug'
    # Also catch a newly created public directory restricted by a host umask.
    (stage / 'public_html/brand').chmod(0o700)
    raw_js = Path('/workspace/visual-parts-delivery/app-B9eZnYus.js')
    for prefix in ['public_html', 'jmac_app/public']:
        target = stage / prefix / 'build/assets' / raw_js.name
        shutil.copyfile(raw_js, target)
        target.chmod(0o644)
    metadata = json.loads((DELIVERY / 'EVIDENCIA.json').read_text())
    archive_path = DELIVERY / metadata['archive']
    with ZipFile(archive_path) as archive:
        assert archive.testzip() is None
        for entry in archive.infolist():
            assert not entry.filename.endswith('.js')
            mode = entry.external_attr >> 16
            assert stat.S_IMODE(mode) == (0o755 if entry.is_dir() else 0o644)
            assert stat.S_ISDIR(mode) if entry.is_dir() else stat.S_ISREG(mode)
    subprocess.run(['unzip', '-oq', str(archive_path), '-d', str(stage)], check=True)
    # Native unzip preserves permissions of existing directories. Exercise the
    # documented cPanel fallback explicitly, rather than crediting it to ZIP.
    restricted_directory = stage / 'public_html/brand'
    assert stat.S_IMODE(restricted_directory.stat().st_mode) == 0o700
    restricted_directory.chmod(0o755)
    for path, original in before.items():
        assert fingerprint(path) == original, ('Private data or index changed', path)
    for name, digest in metadata['file_checksums'].items():
        path = stage / name
        assert hashlib.sha256(path.read_bytes()).hexdigest() == digest, name
        assert path.stat().st_mode & stat.S_IROTH, ('Not readable by the static server', name)
    for prefix in ['public_html', 'jmac_app/public']:
        base = stage / prefix
        if prefix == 'public_html':
            for name in ['brand', 'build', 'build/assets']:
                path = base / name
                assert path.stat().st_mode & stat.S_IXOTH, ('Static directory is not traversable', path)
        manifest = json.loads((base / 'build/manifest.json').read_text())
        for entry in manifest.values():
            assert (base / 'build' / entry['file']).is_file()
    report = {'result': 'PASSED', 'native_unzip': True,
              'old_css_logo_0600_reproduced': True,
              'corrected_files_readable_by_static_server': True,
              'archive_directory_metadata_0755': True,
              'native_unzip_preserves_existing_directory_permissions': True,
              'documented_manual_directory_0755_fallback_tested': True,
              'private_env_storage_root_index_mfa_unchanged': True,
              'all_payload_checksums_match': True, 'both_manifests_resolve': True,
              'browser_stage': str(stage), 'browser_app': str(app)}
    (DELIVERY / 'qa-stage.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report))
except BaseException:
    shutil.rmtree(stage)
    raise
