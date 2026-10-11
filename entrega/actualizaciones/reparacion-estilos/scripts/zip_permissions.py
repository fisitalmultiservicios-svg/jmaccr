"""ZIP metadata for code/assets readable by a shared-hosting web server."""
from pathlib import PurePosixPath
from stat import S_IFDIR, S_IFREG
from zipfile import ZIP_DEFLATED, ZipInfo


def write_entries(archive, entries):
    directories = set()
    for name in entries:
        path = PurePosixPath(name)
        if path.is_absolute() or '..' in path.parts:
            raise ValueError('Unsafe archive path')
        for parent in path.parents:
            # Do not alter permissions of the private application's root.
            if str(parent) not in {'.', 'jmac_app'}:
                directories.add(parent.as_posix() + '/')
    for name in sorted(directories, key=lambda item: (item.count('/'), item)):
        info = ZipInfo(name)
        info.create_system = 3
        info.external_attr = ((S_IFDIR | 0o755) << 16) | 0x10
        info.compress_type = ZIP_DEFLATED
        archive.writestr(info, b'')
    for name, content in sorted(entries.items()):
        info = ZipInfo(name)
        info.create_system = 3
        info.external_attr = (S_IFREG | 0o644) << 16
        info.compress_type = ZIP_DEFLATED
        archive.writestr(info, content)
