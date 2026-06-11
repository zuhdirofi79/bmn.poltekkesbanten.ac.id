<?php

if (! function_exists('bmn_upload_path')) {
    /**
     * Path absolut ke root unggah foto_bmn, atau file di bawahnya.
     * Subpath disupport dengan "/" atau "\" (Windows) — dinormalisasi.
     */
    function bmn_upload_path(string $path = ''): string
    {
        $base = rtrim((string) (config('bmn.foto_path') ?? ''), '/\\');

        if ($base === '') {
            $base = rtrim((string) public_path('uploads/foto_bmn'), '/\\');
        }

        $path = (string) $path;
        if ($path === '' || $path === '/') {
            return $base;
        }

        $path = str_replace('\\', '/', ltrim($path, "/\\"));
        if ($path === '') {
            return $base;
        }

        return $base.'/'.$path;
    }
}

if (! function_exists('bmn_upload_url')) {
    function bmn_upload_url(string $path = ''): string
    {
        $base = rtrim((string) (config('bmn.foto_url') ?? ''), '/');

        if ($base === '') {
            $base = rtrim((string) url('/uploads/foto_bmn'), '/');
        }

        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}

if (! function_exists('bmn_nota_dinas_path')) {
    /**
     * Path absolut (disk) untuk file Nota Dinas Peminjaman BMN.
     *
     * Resolusi:
     *   1. config('bmn.nota_dinas_path')  (env BMN_NOTA_DINAS_BASE_PATH)
     *   2. fallback: public_path('uploads/nota_dinas')
     *
     * Subpath disupport dengan "/" atau "\" (Windows) — dinormalisasi.
     * Sengaja DI-DESIGN sama dengan bmn_upload_path() supaya pada hosting
     * cPanel/Hostinger (docroot = public_html, Laravel di subfolder) admin
     * cukup set 1 env var dan file langsung tersimpan di document root.
     */
    function bmn_nota_dinas_path(string $path = ''): string
    {
        $base = rtrim((string) (config('bmn.nota_dinas_path') ?? ''), '/\\');

        if ($base === '') {
            $base = rtrim((string) public_path('uploads/nota_dinas'), '/\\');
        }

        $path = (string) $path;
        if ($path === '' || $path === '/') {
            return $base;
        }

        $path = str_replace('\\', '/', ltrim($path, "/\\"));
        if ($path === '') {
            return $base;
        }

        return $base.'/'.$path;
    }
}

if (! function_exists('bmn_nota_dinas_url')) {
    /**
     * URL publik untuk file Nota Dinas Peminjaman BMN.
     *
     * Resolusi:
     *   1. config('bmn.nota_dinas_url')  (env BMN_NOTA_DINAS_BASE_URL)
     *   2. fallback: url('/uploads/nota_dinas')
     */
    function bmn_nota_dinas_url(string $path = ''): string
    {
        $base = rtrim((string) (config('bmn.nota_dinas_url') ?? ''), '/');

        if ($base === '') {
            $base = rtrim((string) url('/uploads/nota_dinas'), '/');
        }

        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}

if (! function_exists('bmn_bukti_bayar_sewa_path')) {
    /**
     * Path absolut (disk) untuk file bukti bayar sewa BMN.
     */
    function bmn_bukti_bayar_sewa_path(string $path = ''): string
    {
        $base = rtrim((string) (config('bmn.bukti_bayar_sewa_path') ?? ''), '/\\');

        if ($base === '') {
            $base = rtrim((string) public_path('uploads/bukti_bayar_sewa'), '/\\');
        }

        $path = (string) $path;
        if ($path === '' || $path === '/') {
            return $base;
        }

        $path = str_replace('\\', '/', ltrim($path, "/\\"));
        if ($path === '') {
            return $base;
        }

        return $base.'/'.$path;
    }
}

if (! function_exists('bmn_bukti_bayar_sewa_url')) {
    /**
     * URL publik untuk file bukti bayar sewa BMN.
     */
    function bmn_bukti_bayar_sewa_url(string $path = ''): string
    {
        $base = rtrim((string) (config('bmn.bukti_bayar_sewa_url') ?? ''), '/');

        if ($base === '') {
            $base = rtrim((string) url('/uploads/bukti_bayar_sewa'), '/');
        }

        $path = ltrim($path, '/');

        return $path === '' ? $base : $base.'/'.$path;
    }
}

if (! function_exists('getPersediaanApproverByUnit')) {
    /**
     * Ambil user approver persediaan untuk unit kerja dari config eksplisit.
     */
    function getPersediaanApproverByUnit(string $unitKerja): ?\App\User
    {
        return \App\Support\Persediaan\PersediaanAccess::getPersediaanApproverByUnit($unitKerja);
    }
}

if (! function_exists('bmn_public_upload_path')) {
    function bmn_public_upload_path(string $subdir, string $path = ''): string
    {
        $base = rtrim((string) public_path($subdir), '/\\');
        $path = (string) $path;
        if ($path === '' || $path === '/') {
            return $base;
        }

        $path = str_replace('\\', '/', ltrim($path, "/\\"));

        return $base.'/'.$path;
    }
}
