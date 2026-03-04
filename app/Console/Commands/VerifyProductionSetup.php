<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VerifyProductionSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'production:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la configuración de producción (permisos, directorios, etc.)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando configuración de producción...');
        $this->newLine();

        $errors = [];
        $warnings = [];

        // Verificar directorio de storage
        $this->info('📁 Verificando Storage...');
        try {
            $storagePath = storage_path();
            if (!is_writable($storagePath)) {
                $errors[] = "Storage no es escribible: {$storagePath}";
            } else {
                $this->line("   ✅ Storage escribible: {$storagePath}");
            }

            // Verificar storage/app/public
            $publicStorage = storage_path('app/public');
            if (!file_exists($publicStorage)) {
                if (!mkdir($publicStorage, 0755, true)) {
                    $errors[] = "No se pudo crear: {$publicStorage}";
                } else {
                    $this->line("   ✅ Creado: {$publicStorage}");
                }
            } else {
                if (!is_writable($publicStorage)) {
                    $errors[] = "Storage público no es escribible: {$publicStorage}";
                } else {
                    $this->line("   ✅ Storage público escribible: {$publicStorage}");
                }
            }

            // Verificar directorio de perfiles
            $profilesPath = storage_path('app/public/profiles');
            if (!file_exists($profilesPath)) {
                if (!mkdir($profilesPath, 0755, true)) {
                    $errors[] = "No se pudo crear: {$profilesPath}";
                } else {
                    $this->line("   ✅ Creado: {$profilesPath}");
                }
            } else {
                if (!is_writable($profilesPath)) {
                    $errors[] = "Directorio de perfiles no es escribible: {$profilesPath}";
                } else {
                    $this->line("   ✅ Directorio de perfiles escribible: {$profilesPath}");
                }
            }

            // Verificar fallback: public/images/profiles
            $publicProfilesPath = public_path('images/profiles');
            if (!file_exists($publicProfilesPath)) {
                $warnings[] = "Directorio fallback no existe: {$publicProfilesPath} (se creará automáticamente si es necesario)";
            } else {
                if (!is_writable($publicProfilesPath)) {
                    $warnings[] = "Directorio fallback no es escribible: {$publicProfilesPath}";
                } else {
                    $this->line("   ✅ Directorio fallback escribible: {$publicProfilesPath}");
                }
            }

        } catch (\Exception $e) {
            $errors[] = "Error verificando storage: " . $e->getMessage();
        }

        $this->newLine();

        // Verificar link simbólico de storage
        $this->info('🔗 Verificando link simbólico de storage...');
        $storageLink = public_path('storage');
        if (!file_exists($storageLink)) {
            $warnings[] = "Link simbólico de storage no existe. Ejecuta: php artisan storage:link";
        } else {
            $this->line("   ✅ Link simbólico existe: {$storageLink}");
        }

        $this->newLine();

        // Verificar base de datos
        $this->info('🗄️  Verificando conexión a base de datos...');
        try {
            \DB::connection()->getPdo();
            $this->line("   ✅ Conexión a base de datos exitosa");
        } catch (\Exception $e) {
            $errors[] = "Error de conexión a base de datos: " . $e->getMessage();
        }

        $this->newLine();

        // Resumen
        if (empty($errors) && empty($warnings)) {
            $this->info('✅ ¡Todo está configurado correctamente!');
            return 0;
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Advertencias:');
            foreach ($warnings as $warning) {
                $this->line("   - {$warning}");
            }
            $this->newLine();
        }

        if (!empty($errors)) {
            $this->error('❌ Errores encontrados:');
            foreach ($errors as $error) {
                $this->line("   - {$error}");
            }
            $this->newLine();
            $this->error('Por favor, corrige estos errores antes de continuar.');
            return 1;
        }

        return 0;
    }
}
