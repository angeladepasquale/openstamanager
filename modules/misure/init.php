<?php

include_once __DIR__.'/../../core.php';

if (isset($id_record)) {
    $records = $dbo->fetchArray('SELECT * FROM `mg_unitamisura` WHERE id='.prepare($id_record));
}
