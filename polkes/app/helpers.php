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
