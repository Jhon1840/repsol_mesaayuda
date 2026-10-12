<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Los datos de demostración solo se pueden cargar en local o testing.');
        }
        $this->call(CategorySeeder::class);
        // Re-runnable without duplicating tickets or resetting existing passwords.
        if (User::where('email', 'soporte@demo.test')->exists()) {
            $this->command?->info('La demostración ya fue cargada. No se modificaron los datos existentes.');

            return;
        }
        DB::transaction(function () {
            $people = [
                ['Carlos Mendoza', 'soporte@demo.test', 'support'],
                ['Ana Rojas', 'ana@demo.test', 'support'],
                ['Diego Vargas', 'diego@demo.test', 'support'],
                ['Lucía Fernández', 'usuario@demo.test', 'user'],
                ['Juan Pérez', 'juan@demo.test', 'user'],
                ['María López', 'maria@demo.test', 'user'],
                ['Pedro Salazar', 'pedro@demo.test', 'user'],
                ['Valeria Torres', 'valeria@demo.test', 'user'],
            ];
            $users = collect($people)->map(fn ($p) => User::create(['name' => $p[0], 'email' => $p[1], 'role' => $p[2], 'is_active' => true, 'password' => 'DemoMesa2026!']));
            $examples = [
                ['Sin conexión en la oficina de operaciones', 'Todos los equipos del segundo piso perdieron acceso a internet. El WiFi aparece conectado, pero no podemos abrir ningún sistema.', 'Red / Internet', 'urgent', 'pending'],
                ['No puedo ingresar al sistema de ventas', 'Al iniciar sesión aparece el mensaje «Credenciales inválidas». Necesito acceder para registrar las ventas del día.', 'Acceso / Contraseña', 'high', 'pending'],
                ['Impresora no detectada en contabilidad', 'La impresora compartida dejó de aparecer en mi equipo después de la actualización de Windows.', 'Impresoras', 'medium', 'pending'],
                ['Instalación de herramientas de diseño', 'Solicito la instalación de las herramientas autorizadas para el nuevo proyecto de comunicación.', 'Software', 'low', 'pending'],
                ['Correos con adjuntos no se envían', 'Los mensajes con archivos PDF de más de 5 MB se quedan en la bandeja de salida.', 'Correo', 'high', 'pending'],
                ['Acceso al módulo de reportes', 'Necesito permisos de lectura para consultar los reportes mensuales del área comercial.', 'Sistema interno', 'medium', 'pending'],
                ['VPN se desconecta durante las reuniones', 'La conexión VPN se interrumpe cada 15 minutos desde mi equipo portátil. Ya probé reiniciar el router.', 'Red / Internet', 'urgent', 'in_progress'],
                ['Pantalla externa sin señal', 'El monitor de la sala de reuniones no recibe señal al conectar el cable HDMI.', 'Hardware', 'high', 'in_progress'],
                ['Error al exportar reporte mensual', 'El sistema muestra un error al exportar el reporte de septiembre a Excel.', 'Sistema interno', 'medium', 'in_progress'],
                ['Configuración de correo en equipo nuevo', 'Necesito configurar mi cuenta institucional en el equipo que me entregaron esta semana.', 'Correo', 'medium', 'in_progress'],
                ['Equipo lento al iniciar Windows', 'El equipo tarda varios minutos en iniciar y abrir las aplicaciones principales.', 'Hardware', 'low', 'in_progress'],
                ['Restablecimiento de contraseña institucional', 'No recuerdo mi contraseña y el enlace de recuperación anterior expiró.', 'Acceso / Contraseña', 'high', 'completed'],
                ['Impresora configurada correctamente', 'La impresora del área administrativa no permitía imprimir a doble cara.', 'Impresoras', 'medium', 'completed'],
                ['Actualización del sistema operativo', 'Solicito actualizar el equipo para instalar la versión aprobada del sistema operativo.', 'Software', 'low', 'completed'],
                ['Conexión WiFi restablecida', 'El equipo no encontraba la red de la oficina después de volver de una reunión.', 'Red / Internet', 'medium', 'completed'],
                ['Permisos de carpeta compartida', 'No puedo abrir la carpeta del proyecto aunque formo parte del equipo.', 'Acceso / Contraseña', 'medium', 'completed'],
                ['Teclado con teclas que no responden', 'Varias teclas del teclado dejaron de responder y necesito un reemplazo.', 'Hardware', 'low', 'completed'],
                ['Firma de correo actualizada', 'Solicito actualizar mi firma con el nuevo cargo y teléfono institucional.', 'Correo', 'low', 'completed'],
            ];
            foreach ($examples as $i => [$subject, $description, $category, $priority, $status]) {
                $requester = $users[3 + ($i % 5)];
                $assignee = $status === 'pending' && $i % 2 === 0 ? null : $users[$i % 3];
                $created = now()->subHours($status === 'completed' ? 5 + ($i % 4) : 2 + $i % 7)->subMinutes($i * 3);
                $assigned = $assignee ? $created->copy()->addMinutes(15) : null;
                $started = $status !== 'pending' ? $created->copy()->addMinutes(30) : null;
                $resolved = $status === 'completed' ? now()->subMinutes(15 + ($i % 5) * 20) : null;
                $solution = $status === 'completed' ? 'Se revisó la configuración del servicio, se aplicaron los ajustes necesarios y se verificó el funcionamiento junto al solicitante.' : null;
                $ticket = Ticket::create(['user_id' => $requester->id, 'assigned_to' => $assignee?->id,
                    'category_id' => Category::where('name', $category)->value('id'), 'subject' => $subject, 'description' => $description,
                    'priority' => $priority, 'status' => $status, 'resolution' => $solution, 'resolved_by' => $resolved ? $assignee->id : null,
                    'created_at' => $created, 'updated_at' => $resolved ?? $created, 'assigned_at' => $assigned, 'started_at' => $started, 'resolved_at' => $resolved]);
                $ticket->histories()->create(['user_id' => $requester->id, 'action' => 'created', 'metadata' => [], 'created_at' => $created]);
                if ($assignee) {
                    $ticket->histories()->create(['user_id' => $assignee->id, 'action' => 'assigned', 'metadata' => ['field' => 'assigned_to', 'from' => null, 'to' => $assignee->id], 'created_at' => $assigned]);
                }
                if ($started) {
                    $ticket->histories()->create(['user_id' => $assignee->id, 'action' => 'status_changed', 'metadata' => ['field' => 'status', 'from' => 'pending', 'to' => 'in_progress'], 'created_at' => $started]);
                    $commentAt = $started->copy()->addMinutes(10);
                    $ticket->comments()->create(['user_id' => $assignee->id, 'message' => 'Hola, ya estamos revisando tu solicitud. Te mantendremos al tanto de los avances.', 'created_at' => $commentAt, 'updated_at' => $commentAt]);
                    $ticket->histories()->create(['user_id' => $assignee->id, 'action' => 'commented', 'metadata' => [], 'created_at' => $commentAt]);
                    $ticket->comments()->create(['user_id' => $requester->id, 'message' => 'Muchas gracias. Estoy disponible si necesitan más información.', 'created_at' => $commentAt->copy()->addMinutes(5)]);
                    $ticket->histories()->create(['user_id' => $requester->id, 'action' => 'commented', 'metadata' => [], 'created_at' => $commentAt->copy()->addMinutes(5)]);
                }
                if ($resolved) {
                    $ticket->histories()->create(['user_id' => $assignee->id, 'action' => 'resolved', 'metadata' => ['field' => 'status', 'from' => 'in_progress', 'to' => 'completed', 'resolution' => $solution], 'created_at' => $resolved]);
                }
                if ($i % 4 === 0) {
                    $path = 'tickets/'.$ticket->id.'/diagnostico-demo.txt';
                    $content = "Diagnóstico de ejemplo\nTicket: ".$ticket->code."\n".$description;
                    Storage::disk('local')->put($path, $content);
                    $ticket->attachments()->create(['user_id' => $requester->id, 'path' => $path, 'original_name' => 'diagnostico.txt', 'mime_type' => 'text/plain', 'size' => strlen($content)]);
                    $ticket->histories()->create(['user_id' => $requester->id, 'action' => 'attached', 'metadata' => ['filename' => 'diagnostico.txt'], 'created_at' => $created]);
                }
                foreach ([$requester, $users[0]] as $recipient) {
                    $recipient->notifications()->create(['id' => (string) Str::uuid(), 'type' => TicketActivity::class,
                        'data' => ['ticket_id' => $ticket->id, 'code' => $ticket->code, 'message' => $resolved ? 'Ticket finalizado: '.$subject : 'Solicitud registrada: '.$subject, 'url' => '/tickets/'.$ticket->id], 'read_at' => $i % 3 === 0 ? null : now()]);
                }
            }
        });
        $this->command?->info('Demo cargada: 8 cuentas, 18 tickets, comentarios, historial, archivos y notificaciones.');
    }
}
