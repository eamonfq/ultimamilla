<?php

namespace App\Filament\Pages;

use App\Settings\UltimamillaSettings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Configuracion extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Configuración del sistema';

    protected static ?string $slug = 'configuracion';

    protected string $view = 'filament.pages.configuracion';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(UltimamillaSettings::class);

        $this->form->fill([
            'cupo_global_default' => $settings->cupo_global_default,
            'hora_cierre_reservas' => $settings->hora_cierre_reservas,
            'dias_operativos' => $settings->dias_operativos,
            'umbral_minimo_diario' => $settings->umbral_minimo_diario,
            'devolucion_threshold' => $settings->devolucion_threshold,
            'cumplimiento_sobre_reserva_max' => $settings->cumplimiento_sobre_reserva_max,
            'cumplimiento_consistente_min' => $settings->cumplimiento_consistente_min,
            'cumplimiento_sub_reserva_cupo_max' => $settings->cumplimiento_sub_reserva_cupo_max,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Operación')
                    ->schema([
                        TextInput::make('cupo_global_default')
                            ->label('Cupo global por defecto')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->suffix('paquetes/día')
                            ->helperText('Cupo por defecto cuando un repartidor no tiene cupo personalizado'),

                        TextInput::make('hora_cierre_reservas')
                            ->label('Hora de cierre de reservas')
                            ->required()
                            ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                            ->placeholder('HH:MM')
                            ->helperText('Hora límite (Colombia) del día anterior para editar/cancelar reservas'),

                        CheckboxList::make('dias_operativos')
                            ->label('Días operativos')
                            ->options([
                                0 => 'Domingo',
                                1 => 'Lunes',
                                2 => 'Martes',
                                3 => 'Miércoles',
                                4 => 'Jueves',
                                5 => 'Viernes',
                                6 => 'Sábado',
                            ])
                            ->required()
                            ->columns(4),

                        TextInput::make('umbral_minimo_diario')
                            ->label('Umbral mínimo diario')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix('paquetes/día')
                            ->helperText('Disparar alerta en el dashboard si las reservas para mañana caen por debajo de este número. Poner 0 para desactivar'),
                    ]),

                Section::make('Cálculo de patrones de cumplimiento')
                    ->schema([
                        TextInput::make('devolucion_threshold')
                            ->label('Umbral de devolución')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->helperText('Rutas de Pinit con total ≤ este valor se marcan como devolución y se excluyen del cumplimiento'),

                        TextInput::make('cumplimiento_sobre_reserva_max')
                            ->label('Sobre-reserva máximo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->required()
                            ->helperText('Si entregó menos de este % de lo reservado, se marca sobre_reserva'),

                        TextInput::make('cumplimiento_consistente_min')
                            ->label('Consistente mínimo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->required()
                            ->helperText('Si entregó al menos este % de lo reservado y reservó al menos el % de cupo definido abajo, se marca consistente'),

                        TextInput::make('cumplimiento_sub_reserva_cupo_max')
                            ->label('Sub-reserva cupo máximo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1)
                            ->step(0.01)
                            ->required()
                            ->helperText('Si reservó menos de este % de su cupo (pero entregó bien), se marca sub_reserva'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(UltimamillaSettings::class);

        $settings->cupo_global_default = (int) $data['cupo_global_default'];
        $settings->hora_cierre_reservas = $data['hora_cierre_reservas'];
        $settings->dias_operativos = array_map('intval', $data['dias_operativos']);
        $settings->umbral_minimo_diario = (int) $data['umbral_minimo_diario'];
        $settings->devolucion_threshold = (int) $data['devolucion_threshold'];
        $settings->cumplimiento_sobre_reserva_max = (float) $data['cumplimiento_sobre_reserva_max'];
        $settings->cumplimiento_consistente_min = (float) $data['cumplimiento_consistente_min'];
        $settings->cumplimiento_sub_reserva_cupo_max = (float) $data['cumplimiento_sub_reserva_cupo_max'];

        $settings->save();

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
