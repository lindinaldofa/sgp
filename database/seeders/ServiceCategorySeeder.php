<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Saúde', 'icon' => 'pi pi-heart', 'color' => '#EF4444', 'children' => [
                'Consulta médica', 'Medicamento', 'Leito hospitalar',
            ]],
            ['name' => 'Emprego', 'icon' => 'pi pi-briefcase', 'color' => '#3B82F6', 'children' => [
                'Indicação CLT', 'Indicação serviço público', 'Estágio',
            ]],
            ['name' => 'Infraestrutura', 'icon' => 'pi pi-building', 'color' => '#F59E0B', 'children' => [
                'Asfalto', 'Iluminação', 'Saneamento', 'Calçada',
            ]],
            ['name' => 'Educação', 'icon' => 'pi pi-book', 'color' => '#8B5CF6', 'children' => [
                'Vaga escolar', 'Material didático', 'Transporte escolar',
            ]],
            ['name' => 'Assistência Social', 'icon' => 'pi pi-users', 'color' => '#10B981', 'children' => [
                'Cesta básica', 'Benefício social', 'Habitação',
            ]],
            ['name' => 'Indicação política', 'icon' => 'pi pi-star', 'color' => '#6366F1', 'children' => [
                'Cargo comissionado', 'Nomeação',
            ]],
            ['name' => 'Outros', 'icon' => 'pi pi-ellipsis-h', 'color' => '#6B7280', 'children' => []],
        ];

        foreach (Tenant::all() as $tenant) {
            foreach ($categories as $cat) {
                $parent = ServiceCategory::create([
                    'tenant_id' => $tenant->id,
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'color' => $cat['color'],
                ]);

                foreach ($cat['children'] as $childName) {
                    ServiceCategory::create([
                        'tenant_id' => $tenant->id,
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'color' => $cat['color'],
                    ]);
                }
            }
        }
    }
}
