<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\Reportes\Reportes;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** Reportes de la dueña: en pantalla, en PDF y en Excel, por día, semana o mes. */
class ReporteController extends Controller
{
    public function __construct(private readonly Reportes $reportes) {}

    public function index(Request $request)
    {
        return view('reportes.index', $this->datos($request) + [
            'periodos' => Reportes::PERIODOS,
            'tipos'    => Reportes::TIPOS,
        ]);
    }

    public function pdf(Request $request)
    {
        $datos = $this->datos($request);

        $dompdf = new Dompdf([
            'defaultFont'     => 'DejaVu Sans',  // trae acentos y la ñ
            'isRemoteEnabled' => false,
        ]);
        $dompdf->loadHtml(view('reportes.pdf', $datos)->render(), 'UTF-8');
        $dompdf->setPaper('letter', count($datos['reporte']['columnas']) > 5 ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $datos['archivo'] . '.pdf"',
        ]);
    }

    public function excel(Request $request)
    {
        $datos   = $this->datos($request);
        $reporte = $datos['reporte'];
        $ruta    = tempnam(sys_get_temp_dir(), 'reporte');

        $writer = new Writer();
        $writer->getOptions()->DEFAULT_COLUMN_WIDTH = 16;
        $writer->openToFile($ruta);
        $writer->getCurrentSheet()->setName('Reporte');
        $writer->getCurrentSheet()->setColumnWidth(30, 1);

        $titulo  = (new Style())->setFontBold()->setFontSize(14);
        $gris    = (new Style())->setFontColor('636366');
        $negrita = (new Style())->setFontBold();
        $cabecera = (new Style())->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('1E3A5F');

        $writer->addRow(Row::fromValues([config('whatsapp.negocio.nombre') . ' · ' . $reporte['titulo']], $titulo));
        $writer->addRow(Row::fromValues([$datos['etiqueta']], $gris));
        $writer->addRow(Row::fromValues(['Generado el ' . Carbon::now()->format('d/m/Y H:i') . '. Montos estimados con el precio actual de cada servicio.'], $gris));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(array_map(fn ($c) => $c[0], $reporte['columnas']), $cabecera));

        foreach ($reporte['filas'] as $fila) {
            $writer->addRow($this->filaExcel($fila, $reporte['columnas']));
        }

        if (! empty($reporte['total'])) {
            $writer->addRow($this->filaExcel($reporte['total'], $reporte['columnas'], $negrita));
        }

        if ($reporte['resumen'] !== []) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['Resumen'], $negrita));
            foreach ($reporte['resumen'] as $etiqueta => $valor) {
                $writer->addRow(Row::fromValues([$etiqueta, $valor]));
            }
        }

        if ($reporte['nota']) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$reporte['nota']], $gris));
        }

        $writer->close();

        return response()->download($ruta, $datos['archivo'] . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** Valor que se muestra en pantalla y en PDF. */
    public static function formatear(mixed $celda, string $tipo): string
    {
        [$tipo, $valor] = is_array($celda) ? $celda : [$tipo, $celda];

        if (! is_numeric($valor)) {
            return (string) $valor;
        }

        return match ($tipo) {
            'dinero'     => '$' . number_format((float) $valor, 2),
            'porcentaje' => rtrim(rtrim(number_format((float) $valor, 1), '0'), '.') . ' %',
            'decimal'    => rtrim(rtrim(number_format((float) $valor, 1), '0'), '.'),
            default      => number_format((float) $valor, (float) $valor == (int) $valor ? 0 : 2),
        };
    }

    /** @param array<int, array{0: string, 1: string}> $columnas */
    private function filaExcel(array $fila, array $columnas, ?Style $estilo = null): Row
    {
        $valores = [];
        $estilos = [];

        foreach ($fila as $i => $celda) {
            [$tipo, $valor] = is_array($celda) ? $celda : [$columnas[$i][1] ?? 'texto', $celda];
            $valores[$i] = is_numeric($valor) ? $valor + 0 : (string) $valor;

            $formato = match ($tipo) {
                'dinero'     => '"$"#,##0.00',
                'porcentaje' => '0.0" %"',
                'decimal'    => '0.0',
                default      => null,
            };

            if ($formato && is_numeric($valor)) {
                $s = (new Style())->setFormat($formato);
                if ($estilo) {
                    $s->setFontBold();
                }
                $estilos[$i] = $s;
            }
        }

        return Row::fromValuesWithStyles($valores, $estilo, $estilos);
    }

    /** Filtros comunes a la pantalla y a las descargas. */
    private function datos(Request $request): array
    {
        $filtros = $request->validate([
            'periodo' => ['nullable', 'in:' . implode(',', array_keys(Reportes::PERIODOS))],
            'fecha'   => ['nullable', 'date_format:Y-m-d'],
            'tipo'    => ['nullable', 'in:' . implode(',', array_keys(Reportes::TIPOS))],
        ]);

        $periodo = $filtros['periodo'] ?? 'mes';
        $tipo    = $filtros['tipo'] ?? 'resumen';
        $fecha   = isset($filtros['fecha']) ? Carbon::parse($filtros['fecha']) : Carbon::today();

        [$desde, $hasta, $etiqueta] = $this->reportes->periodo($periodo, $fecha);
        $reporte = $this->reportes->generar($tipo, $desde, $hasta);

        return [
            'periodo'  => $periodo,
            'tipo'     => $tipo,
            'fecha'    => $fecha,
            'etiqueta' => $etiqueta,
            'reporte'  => $reporte,
            'filtros'  => ['periodo' => $periodo, 'fecha' => $fecha->toDateString(), 'tipo' => $tipo],
            'archivo'  => 'reporte-' . Str::slug($reporte['titulo']) . '-' . $periodo . '-' . $fecha->toDateString(),
        ];
    }
}
