<?php

namespace Database\Seeders;

use App\Models\AccessGroup;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['dashboard.view', 'Visualizar painel', 'dashboard'], ['patients.view', 'Visualizar pacientes', 'patients'], ['patients.create', 'Criar pacientes', 'patients'], ['patients.update', 'Editar pacientes', 'patients'], ['patients.delete', 'Excluir pacientes', 'patients'], ['patients.export', 'Exportar histórico em PDF', 'patients'],
            ['clinical_records.view', 'Visualizar registros clínicos', 'clinical_records'], ['clinical_records.create', 'Criar registros clínicos', 'clinical_records'], ['clinical_records.update', 'Editar próprios registros clínicos', 'clinical_records'], ['clinical_records.cancel', 'Cancelar registros clínicos', 'clinical_records'], ['clinical_records.delete', 'Excluir próprios registros clínicos', 'clinical_records'], ['clinical_records.manage_all', 'Gerenciar registros de todos os profissionais', 'clinical_records'],
            ['attachments.download', 'Baixar anexos clínicos', 'attachments'], ['users.manage', 'Gerenciar usuários e seus grupos', 'users'], ['groups.view', 'Visualizar grupos', 'groups'], ['groups.manage', 'Criar grupos e definir permissões', 'groups'], ['permissions.view', 'Visualizar catálogo de permissões', 'permissions'], ['audit_logs.view', 'Visualizar trilha de auditoria', 'audit_logs'],
        ] as [$key, $name, $module]) {
            Permission::updateOrCreate(['key' => $key], ['name' => $name, 'module' => $module]);
        }

        $group = AccessGroup::updateOrCreate(['name' => 'Administrador'], ['description' => 'Acesso integral ao sistema.', 'is_system' => true]);
        $group->permissions()->sync(Permission::pluck('id'));
        $user = User::firstOrCreate(['email' => 'andre@fisio1.com.br'], ['name' => 'André Lauria', 'password' => 'andre', 'is_active' => true]);
        $user->accessGroups()->syncWithoutDetaching([$group->id]);
    }
}
