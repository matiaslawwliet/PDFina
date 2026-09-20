# -*- mode: python ; coding: utf-8 -*-

import os

COMUN = os.path.join(os.path.dirname(os.path.abspath(SPEC)), '..', 'comun')

a = Analysis(
    ['mservice-paw.py'],
    pathex=[COMUN],
    binaries=[],
    datas=[],
    hiddenimports=['pdfina_proceso'],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    noarchive=False,
    optimize=0,
)
pyz = PYZ(a.pure)

exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.datas,
    [],
    name='mservice-paw',
    version="version_paw.txt",
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
)
