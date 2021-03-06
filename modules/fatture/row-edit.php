<?php

include_once __DIR__.'/../../core.php';

// Info contratto
$rs = $dbo->fetchArray('SELECT * FROM co_documenti WHERE id='.prepare($id_record));
$idanagrafica = $rs[0]['idanagrafica'];

if ($module['name'] == 'Fatture di vendita') {
    $dir = 'entrata';
    $conti = 'conti-vendite';
} else {
    $dir = 'uscita';
    $conti = 'conti-acquisti';
}

// Impostazioni per la gestione
$options = [
    'op' => 'manage_riga',
    'action' => 'edit',
    'dir' => $dir,
    'conti' => $conti,
    'idanagrafica' => $idanagrafica,
    'edit_articolo' => false,
];

// Dati della riga
$rsr = $dbo->fetchArray('SELECT * FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND id='.prepare(get('idriga')));

$result = $rsr[0];
$result['prezzo'] = $rsr[0]['subtotale'] / $rsr[0]['qta'];

// Importazione della gestione dedicata
$file = 'riga';
if (!empty($result['is_descrizione'])) {
    $file = 'descrizione';

    $options['op'] = 'manage_articolo';
} elseif (!empty($result['idarticolo'])) {
    $file = 'articolo';

    $options['op'] = 'manage_articolo';
}

echo App::load($file.'.php', $result, $options);
