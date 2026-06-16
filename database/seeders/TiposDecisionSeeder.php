<?php

namespace Database\Seeders;

use App\Models\TipoDecision;
use Illuminate\Database\Seeder;

class TiposDecisionSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['codigo' => 'presupuesto_anual', 'nombre' => 'Aprobación del presupuesto anual', 'descripcion' => 'Aprobación del presupuesto de ingresos y gastos para el siguiente período. Art. 38, Ley 675/2001.', 'tipo_mayoria' => 'simple', 'aplica_en' => ['asamblea'], 'orden' => 1],
            ['codigo' => 'estados_financieros', 'nombre' => 'Aprobación de estados financieros', 'descripcion' => 'Aprobación de los estados de cuentas del período anterior. Art. 38, Ley 675/2001.', 'tipo_mayoria' => 'simple', 'aplica_en' => ['asamblea'], 'orden' => 2],
            ['codigo' => 'eleccion_consejo', 'nombre' => 'Elección del consejo de administración', 'descripcion' => 'Elección de los miembros del consejo de administración. Art. 36, Ley 675/2001.', 'tipo_mayoria' => 'simple', 'aplica_en' => ['asamblea'], 'orden' => 3],
            ['codigo' => 'eleccion_administrador', 'nombre' => 'Elección o ratificación del administrador', 'descripcion' => 'Designación o ratificación del administrador del conjunto. Art. 50, Ley 675/2001.', 'tipo_mayoria' => 'simple', 'aplica_en' => ['asamblea', 'consejo'], 'orden' => 4],
            ['codigo' => 'cuota_administracion', 'nombre' => 'Aprobación de la cuota de administración', 'descripcion' => 'Fijación del valor de la cuota ordinaria de administración. Art. 38, Ley 675/2001.', 'tipo_mayoria' => 'simple', 'aplica_en' => ['asamblea'], 'orden' => 5],
            ['codigo' => 'decision_ordinaria', 'nombre' => 'Otra decisión ordinaria', 'descripcion' => 'Cualquier decisión de la asamblea no tipificada en los artículos de mayoría calificada. Art. 45, Ley 675/2001.', 'tipo_mayoria' => 'simple', 'aplica_en' => ['asamblea', 'consejo'], 'orden' => 6],
            ['codigo' => 'reforma_reglamento', 'nombre' => 'Reforma al reglamento de propiedad horizontal', 'descripcion' => 'Modificación del reglamento de propiedad horizontal. Requiere el 70% del total de coeficientes del conjunto. Art. 46, Ley 675/2001.', 'tipo_mayoria' => 'calificada_70', 'aplica_en' => ['asamblea'], 'orden' => 7],
            ['codigo' => 'cambio_destinacion', 'nombre' => 'Cambio de destinación de bienes comunes', 'descripcion' => 'Cambio de uso o destinación de bienes comunes del conjunto. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.', 'tipo_mayoria' => 'calificada_70', 'aplica_en' => ['asamblea'], 'orden' => 8],
            ['codigo' => 'desafectacion_bienes', 'nombre' => 'Desafectación de bienes comunes no esenciales', 'descripcion' => 'Desafectación del carácter común de bienes no esenciales del conjunto. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.', 'tipo_mayoria' => 'calificada_70', 'aplica_en' => ['asamblea'], 'orden' => 9],
            ['codigo' => 'gravamenes_bienes', 'nombre' => 'Constitución de gravámenes sobre bienes comunes', 'descripcion' => 'Constitución de hipotecas, prendas u otros gravámenes sobre bienes comunes. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.', 'tipo_mayoria' => 'calificada_70', 'aplica_en' => ['asamblea'], 'orden' => 10],
            ['codigo' => 'reconstruccion_mejoras', 'nombre' => 'Obras de reconstrucción o mejoras no urgentes', 'descripcion' => 'Obras de reconstrucción del edificio o mejoras que no sean de urgencia. Requiere el 70% del total de coeficientes. Art. 46, Ley 675/2001.', 'tipo_mayoria' => 'calificada_70', 'aplica_en' => ['asamblea'], 'orden' => 11],
            ['codigo' => 'extincion_regimen', 'nombre' => 'Extinción voluntaria del régimen de PH', 'descripcion' => 'Extinción voluntaria del régimen de propiedad horizontal. Requiere unanimidad de todos los propietarios. Art. 9, Ley 675/2001.', 'tipo_mayoria' => 'unanimidad', 'aplica_en' => ['asamblea'], 'orden' => 12],
        ];

        foreach ($tipos as $tipo) {
            TipoDecision::updateOrCreate(['codigo' => $tipo['codigo']], $tipo);
        }
    }
}
