<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\StudentDetail;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear usuarios de ejemplo con correos reales de UTM
        
        // Usuario Alumno (Angel Noh) - Correo real de alumno UTM
        $alumno1 = User::factory()->create([
            'name' => 'Angel Noh',
            'email' => '24090564@alumno.utmetropolitana.edu.mx',
            'password' => bcrypt('password'), // password por defecto
        ]);
        $alumno1->studentDetail()->create([
            'matricula' => '24090564',
            'carrera' => 'Ingeniería en Computación',
        ]);

        // Usuario Alumno (Mauricio Chale) - Correo real de alumno UTM
        $alumno2 = User::factory()->create([
            'name' => 'Mauricio Chale',
            'email' => '24090565@alumno.utmetropolitana.edu.mx',
            'password' => bcrypt('password'),
        ]);
        $alumno2->studentDetail()->create([
            'matricula' => '24090565',
            'carrera' => 'Ingeniería en Computación',
        ]);

        // Usuario Maestro (Jesús Pech) - Correo real de maestro UTM
        User::factory()->create([
            'name' => 'Jesús Pech',
            'email' => 'jesus.pech@utmetropolitana.edu.mx',
            'password' => bcrypt('password'),
        ]);

        // Usuario Maestro adicional - Correo real de maestro UTM
        User::factory()->create([
            'name' => 'María González',
            'email' => 'maria.gonzalez@utmetropolitana.edu.mx',
            'password' => bcrypt('password'),
        ]);

        // Usuario Administrador Principal
        User::factory()->create([
            'name' => 'Administrador UTM',
            'email' => 'admini@admin.utmetropolitana.edu.mx',
            'password' => bcrypt('12345'),
        ]);
    }
}
