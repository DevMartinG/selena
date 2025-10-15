<?php

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Spatie\SimpleExcel\SimpleExcelWriter;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // return view('welcome');
    return redirect('/admin');
});

Route::get('/plantilla-tenders', function () {
    $writer = SimpleExcelWriter::streamDownload('plantilla-tenders.xlsx');

    $writer->addHeader([
        'N°', 'Nombre o Sigla de la Entidad', 'Fecha de Publicacion', 'Nomenclatura',
        'Reiniciado Desde', 'Objeto de Contratación', 'Descripción de Objeto', 'CUI',
        'Valor Referencial / Valor Estimado', 'Moneda',
        'Absolucion de Consultas / Obs Integracion de Bases', 'Presentacion de Ofertas',
        'Otorgamiento de la Buena Pro', 'Consentimientio de la Buena Pro', 'Estado Actual',
        'RUC del Adjudicado', 'Razon Social del Postor Adjudicado', 'Monto Adjudicado',
        'Fecha de Suscripcion del Contrato', 'Monto Diferencial (VE/VF vs Oferta Economica)',
        'Observaciones', 'OEC / Comité de Selección', 'Ejecucion Contractual', 'Datos del Contrato',
    ]);

    $writer->addRow([
        '1', 'GOBIERNO REGIONAL DE PUNO', '15/09/2025', 'AS-SM-101-2025-OEC/GR PUNO-1',
        '', 'Servicio', 'ALGO', '1234567',
        '443100.00', 'PEN', '20/09/2025', '25/09/2025',
        '01/10/2025', '05/10/2025', 'Consentido',
        '20448767443', 'CONTRATISTA SAC', '392700.00', '10/10/2025', '50400.00',
        'Observaciones...', 'Comité...', 'Ejecución...', 'Contrato...',
    ]);

    flush();
    exit();
})->name('tenders.template');

Route::get('/errores-importacion-tenders', function () {
    $errors = session()->get('tenders_import_errors', []);

    if (empty($errors)) {
        abort(404);
    }

    return Response::streamDownload(function () use ($errors) {
        $writer = SimpleExcelWriter::create('php://output', 'xlsx');

        $writer->addHeader([
            // 'Fila', 'Tipo de Error', 'Detalle', 'Identifier', 'Entidad'
            'Fila', 'Tipo de Error', 'Detalle', 'Nomenclatura',
        ]);

        foreach ($errors as $error) {
            $writer->addRow([
                $error['row'] ?? '',
                $error['type'] ?? '',
                $error['detalle'] ?? '',
                $error['identifier'] ?? '',
                // $error['entity'] ?? '',
            ]);
        }

        $writer->close();
    }, 'errores-importacion-tenders.xlsx');
})->name('tenders.download-errors');

Route::get('/plantilla-seace-tenders', function () {
    $writer = SimpleExcelWriter::streamDownload('plantilla-seace-tenders.xlsx');

    $writer->addHeader([
        'N°', 'Nombre o Sigla de la Entidad', '', 'Nomenclatura',
        '', 'Objeto de Contratación', 'Descripción de Objeto', 
        'Valor Referencial / Valor Estimado', 'Moneda',
        '', 'Procedimiento del cual se reanuda',
    ]);

    $writer->addRow([
        '1', 'GOBIERNO REGIONAL DE PUNO', '', 'AS-SM-101-2025-OEC/GR PUNO-1',
        '', 'Servicio', 'Servicios de consultoría especializada',
        '443100.00', 'PEN', '', 'LP-001-2024',
    ]);

    flush();
    exit();
})->name('seace-tenders.template');

Route::get('/errores-importacion-seace-tenders', function () {
    $errors = session()->get('seace_tenders_import_errors', []);

    if (empty($errors)) {
        abort(404);
    }

    return Response::streamDownload(function () use ($errors) {
        $writer = SimpleExcelWriter::create('php://output', 'xlsx');

        $writer->addHeader([
            'Fila', 'Tipo de Error', 'Detalle', 'Nomenclatura',
        ]);

        foreach ($errors as $error) {
            $writer->addRow([
                $error['row'] ?? '',
                $error['type'] ?? '',
                $error['detalle'] ?? '',
                $error['identifier'] ?? '',
            ]);
        }

        $writer->close();
    }, 'errores-importacion-seace-tenders.xlsx');
})->name('seace-tenders.download-errors');

Route::get('/errores-importacion-usuarios', function () {
    $errors = session()->get('users_import_errors', []);

    if (empty($errors)) {
        abort(404);
    }

    return Response::streamDownload(function () use ($errors) {
        $writer = SimpleExcelWriter::create('php://output', 'xlsx');

        $writer->addHeader([
            'Fila', 'Tipo de Error', 'Detalle', 'Email/NIN',
        ]);

        foreach ($errors as $error) {
            $writer->addRow([
                $error['row'] ?? '',
                $error['type'] ?? '',
                $error['detalle'] ?? '',
                $error['identifier'] ?? '',
            ]);
        }

        $writer->close();
    }, 'errores-importacion-usuarios.xlsx');
})->name('users.download-errors');
