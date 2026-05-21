#!/usr/bin/env python3
"""Extract media files from a .wpress (All-in-One WP Migration) archive.

Handles two cases:
1. Raw gallery/ files (NextGEN photos) — extracted directly
2. UpdraftPlus uploads zip — extracted then unzipped
"""

import os
import re
import sys
import zipfile
import tempfile

HEADER_SIZE = 4377
NAME_SIZE = 255
SIZE_SIZE = 14
MTIME_SIZE = 12
PREFIX_SIZE = 4096

MEDIA_EXTENSIONS = {
    '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.bmp', '.ico', '.pdf',
}

def read_entry(f):
    h = f.read(HEADER_SIZE)
    if len(h) < HEADER_SIZE:
        return None
    name = h[:NAME_SIZE].split(b'\x00')[0].decode('utf-8', errors='replace')
    size_str = h[NAME_SIZE:NAME_SIZE + SIZE_SIZE].split(b'\x00')[0].decode('utf-8', errors='replace')
    prefix = h[NAME_SIZE + SIZE_SIZE + MTIME_SIZE:NAME_SIZE + SIZE_SIZE + MTIME_SIZE + PREFIX_SIZE].split(b'\x00')[0].decode('utf-8', errors='replace')
    try:
        file_size = int(size_str)
    except ValueError:
        return None
    return name, prefix, file_size

def extract_file_data(f, file_size):
    data = bytearray()
    remaining = file_size
    while remaining > 0:
        chunk = f.read(min(remaining, 65536))
        if not chunk:
            break
        data.extend(chunk)
        remaining -= len(chunk)
    return bytes(data)

def skip_file(f, file_size):
    remaining = file_size
    while remaining > 0:
        skip = min(remaining, 65536)
        d = f.read(skip)
        if not d:
            break
        remaining -= len(d)

def main():
    archive = sys.argv[1] if len(sys.argv) > 1 else '/Users/adammoussa/Downloads/communityamb-org-20260519-192147-slgact06uxn1.wpress'
    output = sys.argv[2] if len(sys.argv) > 2 else '/Users/adammoussa/Documents/repositories/communityamb/public/assets/images'

    os.makedirs(output, exist_ok=True)
    gallery_dir = os.path.join(output, 'gallery')
    os.makedirs(gallery_dir, exist_ok=True)

    gallery_count = 0
    uploads_extracted = False

    print(f"Scanning: {archive}")

    with open(archive, 'rb') as f:
        while True:
            entry = read_entry(f)
            if entry is None:
                break

            name, prefix, file_size = entry
            full_path = f'{prefix}/{name}' if prefix else name

            # Extract raw gallery photos (skip _backup duplicates)
            if full_path.startswith('gallery/') and not name.endswith('_backup'):
                ext = os.path.splitext(name)[1].lower()
                if ext in MEDIA_EXTENSIONS:
                    out_path = os.path.join(gallery_dir, full_path[len('gallery/'):])
                    os.makedirs(os.path.dirname(out_path), exist_ok=True)
                    data = extract_file_data(f, file_size)
                    with open(out_path, 'wb') as out_f:
                        out_f.write(data)
                    gallery_count += 1
                    if gallery_count % 20 == 0:
                        print(f"  Gallery: {gallery_count} files...")
                    continue

            # Extract the most recent uploads zip (only the first one)
            if not uploads_extracted and name == 'backup_2024-08-03-1542_Community_Ambulance_Company_5ef28a13c9c2-uploads.zip':
                print(f"  Extracting uploads zip ({file_size / 1024 / 1024:.0f} MB)...")
                with tempfile.NamedTemporaryFile(suffix='.zip', delete=False) as tmp:
                    tmp_path = tmp.name
                    remaining = file_size
                    while remaining > 0:
                        chunk = f.read(min(remaining, 65536))
                        if not chunk:
                            break
                        tmp.write(chunk)
                        remaining -= len(chunk)

                print("  Unzipping uploads...")
                upload_count = 0
                with zipfile.ZipFile(tmp_path, 'r') as zf:
                    for info in zf.infolist():
                        if info.is_dir():
                            continue
                        ext = os.path.splitext(info.filename)[1].lower()
                        if ext not in MEDIA_EXTENSIONS:
                            continue
                        base = os.path.splitext(os.path.basename(info.filename))[0]
                        if re.search(r'-\d+x\d+$', base):
                            continue
                        out_path = os.path.join(output, 'uploads', info.filename)
                        os.makedirs(os.path.dirname(out_path), exist_ok=True)
                        with zf.open(info) as src, open(out_path, 'wb') as dst:
                            while True:
                                chunk = src.read(65536)
                                if not chunk:
                                    break
                                dst.write(chunk)
                        upload_count += 1
                        if upload_count % 50 == 0:
                            print(f"  Uploads: {upload_count} files...")

                os.unlink(tmp_path)
                uploads_extracted = True
                print(f"  Uploads done: {upload_count} files")
                continue

            # Also extract uploads2.zip
            if not uploads_extracted and name == 'backup_2024-08-03-1542_Community_Ambulance_Company_5ef28a13c9c2-uploads2.zip':
                print(f"  Extracting uploads2 zip ({file_size / 1024 / 1024:.0f} MB)...")
                with tempfile.NamedTemporaryFile(suffix='.zip', delete=False) as tmp:
                    tmp_path = tmp.name
                    remaining = file_size
                    while remaining > 0:
                        chunk = f.read(min(remaining, 65536))
                        if not chunk:
                            break
                        tmp.write(chunk)
                        remaining -= len(chunk)

                print("  Unzipping uploads2...")
                upload_count = 0
                with zipfile.ZipFile(tmp_path, 'r') as zf:
                    for info in zf.infolist():
                        if info.is_dir():
                            continue
                        ext = os.path.splitext(info.filename)[1].lower()
                        if ext not in MEDIA_EXTENSIONS:
                            continue
                        base = os.path.splitext(os.path.basename(info.filename))[0]
                        if re.search(r'-\d+x\d+$', base):
                            continue
                        out_path = os.path.join(output, 'uploads', info.filename)
                        if os.path.exists(out_path):
                            continue
                        os.makedirs(os.path.dirname(out_path), exist_ok=True)
                        with zf.open(info) as src, open(out_path, 'wb') as dst:
                            while True:
                                chunk = src.read(65536)
                                if not chunk:
                                    break
                                dst.write(chunk)
                        upload_count += 1
                        if upload_count % 50 == 0:
                            print(f"  Uploads2: {upload_count} files...")

                os.unlink(tmp_path)
                print(f"  Uploads2 done: {upload_count} files")
                continue

            skip_file(f, file_size)

    print(f"\nComplete: {gallery_count} gallery files extracted")

if __name__ == '__main__':
    main()
