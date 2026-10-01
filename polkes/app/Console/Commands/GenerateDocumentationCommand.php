<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Documentation\DocumentationGeneratorService;
use Illuminate\Console\Command;
use Throwable;

class GenerateDocumentationCommand extends Command
{
    protected $signature = 'documentation:generate
                            {--placeholders : Paksa regenerate screenshot placeholder}
                            {--audit-only : Hanya tampilkan hasil audit tanpa generate PDF}';

    protected $description = 'Generate PDF Panduan Penggunaan SIM-BMN Poltekkes Kemenkes Banten dari audit source code';

    public function handle(DocumentationGeneratorService $generator): int
    {
        $this->info('Memulai audit aplikasi SIM-BMN...');

        try {
            $audit = $generator->auditApplication();
        } catch (Throwable $e) {
            $this->error('Audit gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Route bernama', (string) $audit['route_count']],
                ['Controller files', (string) $audit['controller_count']],
                ['Model files', (string) $audit['model_count']],
                ['View files', (string) $audit['view_count']],
                ['Modul terdeteksi', (string) count($audit['modules'])],
            ]
        );

        $moduleRows = [];
        foreach ($audit['modules'] as $key => $module) {
            $moduleRows[] = [
                $key,
                $module['name'],
                $module['status'],
                (string) count($module['routes'] ?? []),
            ];
        }
        $this->table(['Key', 'Modul', 'Status', 'Routes'], $moduleRows);

        if ($this->option('audit-only')) {
            $this->warn('Mode --audit-only: PDF tidak dibuat.');

            return self::SUCCESS;
        }

        $this->info('Merender PDF DomPDF (A4)...');

        try {
            $result = $generator->generate((bool) $this->option('placeholders'));
        } catch (Throwable $e) {
            $this->error('Generate PDF gagal: '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }

        $this->line('');
        $this->info('PDF berhasil dibuat.');
        $this->line('Lokasi absolut : '.$result['pdf_path']);
        $this->line('Lokasi relatif : '.$result['relative_path']);
        $this->line('Screenshot dir : '.$generator->ensureScreenshotDirectory());
        $this->line('Jumlah screenshot: '.count($result['screenshots']));

        return self::SUCCESS;
    }
}
