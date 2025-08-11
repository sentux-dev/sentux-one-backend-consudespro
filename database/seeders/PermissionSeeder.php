<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar la caché de permisos para asegurar que se apliquen los cambios
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'sanctum'; // Usamos la guardia de API para todas las operaciones

        // --- DEFINICIÓN DE PERMISOS POR MÓDULO ---

        // Módulo de Dashboard
        Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => $guardName], ['label' => 'Ver Dashboard General']);

        // Módulo de CRM
        Permission::firstOrCreate(['name' => 'contacts.view', 'guard_name' => $guardName], ['label' => 'Ver Contactos']);
        Permission::firstOrCreate(['name' => 'contacts.view.own', 'guard_name' => $guardName], ['label' => 'Ver Mis Contactos']);
        Permission::firstOrCreate(['name' => 'contacts.create', 'guard_name' => $guardName], ['label' => 'Crear Contactos']);
        Permission::firstOrCreate(['name' => 'contacts.edit', 'guard_name' => $guardName], ['label' => 'Editar Contactos']);
        Permission::firstOrCreate(['name' => 'contacts.delete', 'guard_name' => $guardName], ['label' => 'Eliminar/Desactivar Contactos']);

        Permission::firstOrCreate(['name' => 'companies.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Empresas']);
        
        Permission::firstOrCreate(['name' => 'deals.view', 'guard_name' => $guardName], ['label' => 'Ver Deals']);
        Permission::firstOrCreate(['name' => 'deals.view.own', 'guard_name' => $guardName], ['label' => 'Ver Mis Deals']);
        Permission::firstOrCreate(['name' => 'deals.create', 'guard_name' => $guardName], ['label' => 'Crear Deals']);
        Permission::firstOrCreate(['name' => 'deals.edit', 'guard_name' => $guardName], ['label' => 'Editar Deals']);
        Permission::firstOrCreate(['name' => 'deals.delete', 'guard_name' => $guardName], ['label' => 'Eliminar Deals']);

        Permission::firstOrCreate(['name' => 'tasks.view', 'guard_name' => $guardName], ['label' => 'Ver Tareas']);
        Permission::firstOrCreate(['name' => 'tasks.view.own', 'guard_name' => $guardName], ['label' => 'Ver Mis Tareas']);

        // Módulo de Sincronizador de Leads
        Permission::firstOrCreate(['name' => 'leads.inbox.view', 'guard_name' => $guardName], ['label' => 'Ver Bandeja de Leads']);
        Permission::firstOrCreate(['name' => 'leads.inbox.process', 'guard_name' => $guardName], ['label' => 'Procesar Leads Manualmente']);
        Permission::firstOrCreate(['name' => 'leads.inbox.import', 'guard_name' => $guardName], ['label' => 'Importar Leads desde Archivo']);
        
        // Módulo de Automatización (Workflows)
        Permission::firstOrCreate(['name' => 'workflows.view', 'guard_name' => $guardName], ['label' => 'Ver Workflows']);
        Permission::firstOrCreate(['name' => 'workflows.manage', 'guard_name' => $guardName], ['label' => 'Crear/Editar Workflows']);
        Permission::firstOrCreate(['name' => 'workflows.delete', 'guard_name' => $guardName], ['label' => 'Eliminar Workflows']);

        // Módulo de Real Estate
        Permission::firstOrCreate(['name' => 'projects.view', 'guard_name' => $guardName], ['label' => 'Ver Proyectos Inmobiliarios']);
        Permission::firstOrCreate(['name' => 'projects.create', 'guard_name' => $guardName], ['label' => 'Crear Proyectos']);
        Permission::firstOrCreate(['name' => 'projects.edit', 'guard_name' => $guardName], ['label' => 'Editar Proyectos']);
        Permission::firstOrCreate(['name' => 'projects.delete', 'guard_name' => $guardName], ['label' => 'Eliminar Proyectos']);
        Permission::firstOrCreate(['name' => 'lots.edit', 'guard_name' => $guardName], ['label' => 'Editar Lotes']);
        Permission::firstOrCreate(['name' => 'projects.settings.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Modelos y Extras de Proyectos']);

        // Módulo de Marketing
        Permission::firstOrCreate(['name' => 'campaigns.view', 'guard_name' => $guardName], ['label' => 'Ver Campañas de Marketing']);
        Permission::firstOrCreate(['name' => 'campaigns.manage', 'guard_name' => $guardName], ['label' => 'Crear/Editar Campañas']);
        Permission::firstOrCreate(['name' => 'campaigns.send', 'guard_name' => $guardName], ['label' => 'Enviar Campañas']);
        Permission::firstOrCreate(['name' => 'campaigns.delete', 'guard_name' => $guardName], ['label' => 'Eliminar Campañas']);
        Permission::firstOrCreate(['name' => 'campaigns.reports.view', 'guard_name' => $guardName], ['label' => 'Ver Reportes de Campañas']);
        Permission::firstOrCreate(['name' => 'audiences.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Listas y Segmentos']);

        // Módulo de Administración y Configuración
        Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => $guardName], ['label' => 'Ver Usuarios']);
        Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => $guardName], ['label' => 'Crear Usuarios']);
        Permission::firstOrCreate(['name' => 'users.edit', 'guard_name' => $guardName], ['label' => 'Editar Usuarios (y asignar roles)']);
        Permission::firstOrCreate(['name' => 'users.delete', 'guard_name' => $guardName], ['label' => 'Eliminar Usuarios']);
        
        Permission::firstOrCreate(['name' => 'roles.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Roles y Permisos']);
        Permission::firstOrCreate(['name' => 'settings.integrations.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Integraciones']);
        Permission::firstOrCreate(['name' => 'settings.lead_sources.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Fuentes de Leads']);
        Permission::firstOrCreate(['name' => 'settings.deals.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Pipelines de Deals']);
        Permission::firstOrCreate(['name' => 'settings.custom_fields.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Campos Personalizados']);
        Permission::firstOrCreate(['name' => 'settings.disqualification_reasons.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Razones de Descalificación']);
        Permission::firstOrCreate(['name' => 'settings.contact_statuses.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Estados de Contacto']);
        Permission::firstOrCreate(['name' => 'settings.origins.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Orígenes de Contacto']);
        Permission::firstOrCreate(['name' => 'settings.marketing_campaigns.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Campañas Publicitarias']);
        Permission::firstOrCreate(['name' => 'settings.sequences.manage', 'guard_name' => $guardName], ['label' => 'Gestionar Secuencias de Contacto']);

        // --- CREACIÓN DE ROLES Y ASIGNACIÓN DE PERMISOS ---

        // Rol de Administrador (tiene acceso a todo)
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guardName]);
        $adminRole->givePermissionTo(Permission::where('guard_name', $guardName)->get());

        // Rol de Vendedor (ejemplo de un rol con acceso limitado)
        $sellerRole = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => $guardName]);
        $sellerRole->givePermissionTo([
            'dashboard.view',
            'contacts.view.own',
            'contacts.create',
            'contacts.edit',
            'deals.view.own',
            'deals.create',
            'deals.edit',
            'projects.view',
            'lots.edit',
            'tasks.view.own',
        ]);

        // Rol de Gerente de Ventas (ejemplo)
        $salesManagerRole = Role::firstOrCreate(['name' => 'gerente_ventas', 'guard_name' => $guardName]);
        $salesManagerRole->givePermissionTo([
            'dashboard.view',
            'contacts.view',
            'contacts.create',
            'contacts.edit',
            'deals.view',
            'deals.create',
            'deals.edit',
            'projects.view',
            'lots.edit',
            'tasks.view'
        ]);
        
        // Rol de Marketing (ejemplo)
        $marketingRole = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => $guardName]);
        $marketingRole->givePermissionTo([
            'dashboard.view',
            'contacts.view',
            'leads.inbox.view',
            'leads.inbox.process',
            'leads.inbox.import',
            'workflows.view',
            'workflows.manage',
            'campaigns.view',
            'campaigns.manage',
            'campaigns.send',
            'campaigns.reports.view',
            'audiences.manage'        
        ]);
    }
}