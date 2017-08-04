<?php

include_once __DIR__.'/../../core.php';

$module_name = 'Interventi';

$id_module = $html->form('id_module');

$fields = [];
$select = '*';
$datas = $dbo->fetchArray('SELECT * FROM zz_views WHERE id_module='.$id_module.' AND id IN (SELECT id_vista FROM zz_group_view WHERE id_gruppo=(SELECT idgruppo FROM zz_users WHERE idutente='.$_SESSION['idutente'].')) ORDER BY `order` ASC');
if ($datas != null) {
    $select = '';
    foreach ($datas as $data) {
        $select .= $data['query']." AS '".$data['name']."',";
        if ($data['enabled']) {
            array_push($fields, trim($data['name']));
        }
    }
    $select = substr($select, 0, strlen($select) - 1);
}

$module = $dbo->fetchArray('SELECT * FROM zz_modules WHERE id="'.$id_module.'"')[0];
$module_query = ($module['options2'] != '') ? $module['options2'] : $module['options'];

$module_query = str_replace('|select|', $select, $module_query);
$module_query = str_replace('|period_start|', $_SESSION['period_start'], $module_query);
$module_query = str_replace('|period_end|', $_SESSION['period_end'], $module_query);
$module_dir = $module['directory'];

$search_filters = [];

if (is_array($_SESSION['module_'.$id_module])) {
    foreach ($_SESSION['module_'.$id_module] as $field_name => $field_value) {
        if ($field_value != '') {
            $field_name = str_replace('search_', '', $field_name);
            $field_name = str_replace('__', ' ', $field_name);
            array_push($search_filters, '`'.$field_name.'` LIKE "%'.$field_value.'%"');
        }
    }
}

if (sizeof($search_filters) > 0) {
    $module_query = str_replace('1=1', '1=1 AND ('.implode(' AND ', $search_filters).') ', $module_query);
}

// Aggiungo eventuali filtri dei permessi
if ($additional_where[$rs[0]['name']] != '') {
    $module_query = str_replace('1=1', '1=1 '.$additional_where[$rs[0]['name']], $module_query);
}

$rsi = $dbo->fetchArray($module_query);

// Se il cliente è uno solo carico la sua intestazione, altrimenti la lascio in bianco
$idcliente = $rsi[0]['idanagrafica'];
$singolo_cliente = true;
for ($i = 0; $i < sizeof($rsi) && $singolo_cliente; ++$i) {
    if ($rsi[$i]['idanagrafica'] != $idcliente) {
        $singolo_cliente = false;
    }
}

if (!$singolo_cliente) {
    $idcliente = '';
}

// carica report html
$report = file_get_contents($docroot.'/templates/riepilogo_interventi/intervento.html');
$body = file_get_contents($docroot.'/templates/riepilogo_interventi/intervento_body.html');

if (!$singolo_cliente) {
    $body = str_replace('Spett.le', '', $body);
}

include_once $docroot.'/templates/pdfgen_variables.php';

$totrows = sizeof($rsi);
$totale_km = 0.00;
$totale_ore = 0.00;
$totale = 0.00;
$totale_calcolato = 0.00;
$info_intervento = [];
$ore = [];
$km = [];
$ntecnici = [];
$tecnici = [];
$costi_orari = [];
$costi_km = [];
$diritto_chiamata = [];

$costo_ore_cons = [];
$costo_km_cons = [];
$diritto_chiamata_cons = [];

$idinterventi = ['0'];

if ($totrows > 0) {
    for ($i = 0; $i < $totrows; ++$i) {
        // Lettura dati dei tecnici dell'intervento corrente
        $query = 'SELECT *, ( ( TIME_TO_SEC(orario_fine)-TIME_TO_SEC(orario_inizio) )  ) AS t, (SELECT ragione_sociale FROM an_anagrafiche WHERE idanagrafica=idtecnico) AS nome_tecnico FROM in_interventi_tecnici WHERE idintervento="'.$rsi[$i]['id'].'"';
        $rs = $dbo->fetchArray($query);
        $n_tecnici = sizeof($rs);

        $riga_tecnici = "<table><tr><td style='border:0px solid transparent;' colspan='2'><div style='width:75mm;'></div></td></tr>\n";
        $t = 0;

        for ($j = 0; $j < $n_tecnici; ++$j) {
            $riga_tecnici .= "<tr><td valign='top' style='border:0px solid transparent;' align='left' >\n".$rs[$j]['nome_tecnico']."\n</td>\n";
            $riga_tecnici .= "<td valign='bottom'  style='border:0px solid transparent;' align='right'>\n".Translator::dateToLocale($rs[$j]['orario_inizio']).' - '.Translator::timeToLocale($rs[$j]['orario_inizio']).'-'.Translator::timeToLocale($rs[$j]['orario_fine'])."\n";
            $riga_tecnici .= "</td></tr>\n";

            // Conteggio ore totali
            $t += round($rs[$j]['t'] / 60 / 60, 2);

            array_push($costi_orari, floatval($rs[$j]['prezzo_ore_unitario']));
            array_push($costi_km, floatval($rs[$j]['prezzo_km_unitario']));
            array_push($diritto_chiamata, floatval($rs[$j]['prezzo_dirittochiamata']));

            array_push($costo_ore_cons, floatval($rs[$j]['prezzo_ore_consuntivo']));
            array_push($costo_km_cons, floatval($rs[$j]['prezzo_km_consuntivo']));
            array_push($diritto_chiamata_cons, floatval($rs[$j]['prezzo_dirittochiamata_consuntivo']));
            array_push($km, floatval($rs[$j]['km']));
            $totale_km += floatval($rs[$j]['km']);
        }

        $riga_tecnici .= "</table>\n";

        $line = '<span>Intervento <b>'.$rsi[$i]['id'].'</b> del <b>'.$rsi[$i]['Data inizio'].":</b><br/><small style='color:#444;'>".nl2br($rsi[$i]['richiesta'])."</small></span><br/>\n";

        // Se l'elenco non è di un singolo cliente stampo anche la sua ragione sociale
        if (!$singolo_cliente) {
            $line .= '<br/><span><small><b>Cliente:</b> '.$rsi[$i]['Ragione sociale']."</small></span>\n";
        }

        array_push($info_intervento, $line);

        array_push($ntecnici, $n_tecnici);
        array_push($tecnici, $riga_tecnici);
        array_push($ore, $t);
        $totale_ore += floatval($t);
        $totale_dirittochiamata += floatval($rs[$i]['prezzo_dirittochiamata']);
        array_push($idinterventi, "'".$rsi[$i]['id']."'");
    }
}

$body .= '<big><big><b>RIEPILOGO INTERVENTI DAL '.Translator::dateToLocale($_SESSION['period_start']).' al '.Translator::dateToLocale($_SESSION['period_end'])."</b></big></big><br/><br/>\n";

// Sostituisco i valori tra | | con il valore del campo del db
$body .= preg_replace('/|(.+?)|/', $rsi[0]['${1}'], $body);

if (sizeof($info_intervento) > 0) {
    // Tabella con riepilogo interventi, km e ore
    $body .= "<table class=\"table_values\" style=\"table-layout:fixed;\" border=\"0\">\n";
    $body .= "<thead>\n";
    $body .= "<tr><th align=\"left\" style=\"width:75mm;\">\n";
    $body .= "<span>Interventi</span>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\" style=\"width:15mm;\">\n";
    $body .= "<span>km</span>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\" style=\"width:15mm;\">\n";
    $body .= "<span>Costo unitario al km</span>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\" style=\"width:15mm;\">\n";
    $body .= "<span>Ore</span>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\" style=\"width:15mm;\">\n";
    $body .= "<span>Costo unitario all&rsquo;ora</span>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\" style=\"width:15mm;\">\n";
    $body .= "<span>Diritto chiamata</span>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\" style=\"width:15mm;\">\n";
    $body .= "<span>Subtotale</span>\n";
    $body .= "</th></tr>\n";
    $body .= "</thead>\n";

    $body .= "<tbody>\n";

    // Tabella con i dati
    for ($i = 0; $i < sizeof($info_intervento); ++$i) {
        $subtotale_consuntivo = floatval($costo_ore_cons[$i] + $costo_km_cons[$i] + $diritto_chiamata_cons[$i]);
        $totale_consuntivo += $subtotale_consuntivo;

        $subtotale_calcolato = $costi_orari[$i] * $ore[$i] + $costi_km[$i] * $km[$i] + $diritto_chiamata[$i];
        $totale_calcolato += $subtotale_calcolato;

        $body .= "<tr><td>\n";
        $body .= '<div style="width:75mm;"><span>'.$info_intervento[$i].'<br/><span style="font-size:10px; color:#777;"><b>Tecnici:</b></span></span><br/><small>'.$tecnici[$i]."</small></div>\n";
        $body .= "</td>\n";

        // Totale km
        $body .= '<td align="center" valign="top">';
        $body .= '<div style="width:15mm;"><span>'.Translator::numberToLocale($km[$i], 2)."</span></div>\n";
        $body .= "</td>\n";

        // Costo km
        $body .= '<td align="center" valign="top">';
        $body .= '<div style="width:15mm;"><span>'.Translator::numberToLocale($costi_km[$i], 2)."</span></div>\n";
        $body .= "</td>\n";

        // Totale ore
        $body .= '<td align="center" valign="top">';
        $body .= '<div style="width:15mm;"><span>'.Translator::numberToLocale($ore[$i], 2)."</span></div>\n";
        $body .= "</td>\n";

        // Costo ore
        $body .= '<td align="center" valign="top">';
        $body .= '<div style="width:15mm;"><span>'.Translator::numberToLocale($costi_orari[$i], 2)."</span></div>\n";
        $body .= "</td>\n";

        // Diritto chiamata
        $body .= '<td align="center" valign="top">';
        $body .= '<div style="width:15mm;"><span>'.Translator::numberToLocale($diritto_chiamata[$i], 2)."</span></div>\n";
        $body .= "</td>\n";

        // Subtot
        $body .= '<td align="center" valign="top">';
        $body .= '<div style="width:15mm;"><span>'.Translator::numberToLocale($subtotale_calcolato, 2)."</span></div>\n";
        $body .= "</td></tr>\n";
    }

    $body .= "<tr><td style='border:0px;' align=\"right\">\n";
    $body .= "<b>Totale:</b>\n";
    $body .= "</td>\n";

    // Totale costo km
    $body .= "<td align=\"center\">\n";
    $body .= '<b>'.Translator::numberToLocale($totale_km, 2)."</b>\n";
    $body .= "</td>\n";
    $body .= "<td></td>\n";

    // Totale costo ore
    $body .= "<td align=\"center\">\n";
    $body .= '<b>'.Translator::numberToLocale($totale_ore, 2)."</b>\n";
    $body .= "</td>\n";
    $body .= "<td></td>\n";

    // Totale diritto chiamata
    $body .= "<td align=\"center\">\n";
    $body .= '<b>'.Translator::numberToLocale($totale_dirittochiamata, 2)."</b>\n";
    $body .= "</td>\n";

    $body .= "<td align=\"center\" bgcolor=\"#dddddd\">\n";
    $body .= '<b>'.Translator::numberToLocale($totale_calcolato, 2)." &euro;</b>\n";
    $body .= "</td></tr>\n";

    // Riga dello sconto
    $sconto = $totale_calcolato - $totale_consuntivo;
    if ($sconto != 0) {
        /*
        $body .= "<tr><td style=\"border:0px;\" align=\"right\" colspan=\"6\">\n";
        $body .= "<b>Arrotondamenti:</b>\n";
        $body .= "</td><td align=\"center\">\n";
        $body .= "<b>".Translator::numberToLocale( -$sconto, 2)." &euro;</b>\n";
        $body .= "</td></tr>\n\n";
        */

        $body .= "<tr><td style=\"border:0px;\" align=\"right\" colspan=\"6\">\n";
        $body .= "<b>Totale scontato:</b>\n";
        $body .= "</td><td align=\"center\">\n";
        $body .= '<b>'.Translator::numberToLocale($totale_calcolato - $sconto, 2)." &euro;</b>\n";
        $body .= "</td></tr>\n";
    }

    $totale_intervento_scontato = $totale_calcolato - $sconto;
    $body .= "</tbody>\n";
    $body .= "</table><br/>\n";
}

// Conteggio articoli utilizzati
$query = "SELECT *, (SELECT percentuale FROM co_iva WHERE id=(SELECT idiva_vendita FROM mg_articoli WHERE id=idarticolo)) AS prciva_vendita, (SELECT orario_inizio FROM in_interventi_tecnici WHERE idintervento=mg_articoli_interventi.idintervento GROUP BY idintervento HAVING idintervento=mg_articoli_interventi.idintervento) AS data_intervento, (SELECT prc_guadagno FROM mg_listini WHERE id=(SELECT idlistino FROM an_anagrafiche WHERE idanagrafica=(SELECT idanagrafica FROM in_interventi WHERE id=mg_articoli_interventi.idintervento) ) ) AS prc_guadagno, (SELECT codice FROM mg_articoli WHERE id=idarticolo) AS codice_art, GROUP_CONCAT( CONCAT_WS(lotto, 'Lotto: ', ', '), CONCAT_WS(serial, 'SN: ', ', '), CONCAT_WS(altro, 'Altro: ', '') SEPARATOR '<br/>') AS codice, SUM(qta) AS sumqta FROM `mg_articoli_interventi` GROUP BY idarticolo, idintervento, lotto HAVING idintervento IN(".implode(',', $idinterventi).") AND NOT idarticolo='0' ORDER BY idarticolo ASC";
$rs2 = $dbo->fetchArray($query);
if (sizeof($rs2) > 0) {
    $body .= "<table style=\"width:100%;\" class=\"table_values\" cellspacing=\"2\" cellpadding=\"5\" style=\"border-color:#aaa;\">\n";
    $body .= "<thead>\n";
    $body .= "<tr><th align='center' colspan='4'><b>Materiale utilizzato per gli interventi</b></th></tr>\n";

    $body .= "<tr><th style=\"width:130mm;\">\n";
    $body .= "<b>Articolo</b>\n";
    $body .= "</th>\n";

    $body .= "<th style=\"width:10mm;\" align=\"center\">\n";
    $body .= "<b>Q.tà</b>\n";
    $body .= "</th>\n";

    $body .= "<th style=\"width:20mm;\" align=\"center\">\n";
    $body .= "<b>Prezzo unitario</b>\n";
    $body .= "</th>\n";

    $body .= "<th style=\"width:20mm;\" align=\"center\">\n";
    $body .= "<b>Subtot</b>\n";
    $body .= "</th></tr>\n";
    $body .= "</thead>\n";

    $totale_articoli = 0.00;

    for ($i = 0; $i < sizeof($rs2); ++$i) {
        // Articolo
        $body .= "<tr><td class='first_cell'>\n";
        $body .= '<span>'.nl2br($rs2[$i]['descrizione'])."</span>\n";
        if ($rs2[$i]['codice'] != '' && $rs2[$i]['codice'] != 'Lotto: , SN: , Altro: ') {
            $body .= '<br/><small>'.$rs2[$i]['codice']."</small>\n";
        }

        $body .= '<br/><span><small style="color:#777;">Intervento '.$rs2[$i]['idintervento'].' del '.Translator::dateToLocale($rs2[$i]['data_intervento'])."</small></span>\n";
        $body .= "</td>\n";

        // Quantità
        $qta = $rs2[$i]['sumqta'];
        $body .= "<td class='table_cell' align='center'>\n";
        $body .= '<span>'.$rs2[$i]['sumqta']."</span>\n";
        $body .= "</td>\n";

        // Prezzo unitario
        $body .= "<td class='table_cell' align='center'>\n";
        $netto = $rs2[$i]['prezzo_vendita'];
        $netto = $netto + $netto / 100 * $rs2[$i]['prc_guadagno'];
        $iva = $netto / 100 * $rs2[$i]['prciva_vendita'];
        $body .= '<span>'.Translator::numberToLocale($netto, 2)." &euro;</span>\n";
        $body .= "</td>\n";

        // Prezzo di vendita
        $body .= "<td class='table_cell' align='center'>\n";
        $body .= '<span><span>'.Translator::numberToLocale($netto * $qta, 2)."</span> &euro;</span>\n";
        $body .= "</td></tr>\n";
        $totale_articoli += $netto * $qta;
    }

    // Totale spesa articoli
    $body .= "<tr><td colspan=\"3\" align=\"right\">\n";
    $body .= "<b>TOTALE MATERIALE UTILIZZATO:</b>\n";
    $body .= "</td>\n";

    $body .= "<td align=\"center\" bgcolor=\"#dddddd\">\n";
    $body .= '<b>'.Translator::numberToLocale($totale_articoli, 2)." &euro;</b>\n";
    $body .= "</td></tr>\n";
    $body .= "</table><br/>\n";
}

// Conteggio spese aggiuntive
$query = 'SELECT *, (SELECT orario_inizio FROM in_interventi_tecnici WHERE idintervento=in_righe_interventi.idintervento GROUP BY idintervento HAVING idintervento=in_righe_interventi.idintervento ORDER BY orario_inizio) AS data_intervento FROM in_righe_interventi WHERE idintervento IN('.implode(',', $idinterventi).') ORDER BY id ASC';
$rs2 = $dbo->fetchArray($query);

if (sizeof($rs2) > 0) {
    $body .= "<table style=\"width:100%;\" class=\"table_values\" cellspacing=\"2\" cellpadding=\"5\" style=\"border-color:#aaa;\">\n";
    $body .= "<col width=\"335\"><col width=\"50\"><col width=\"142\"><col width=\"142\">\n";

    $body .= "<thead>\n";
    $body .= "<tr><th align='center' colspan='4'><b>Spese aggiuntive</b></th></tr>\n";

    $body .= "<tr><th>\n";
    $body .= "<b>Descrizione</b>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\">\n";
    $body .= "<b>Q.tà</b>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\">\n";
    $body .= "<b>Prezzo unitario</b>\n";
    $body .= "</th>\n";

    $body .= "<th align=\"center\">\n";
    $body .= "<b>Subtot</b>\n";
    $body .= "</th></tr>\n";
    $body .= "</thead>\n";

    $totale_spese = 0.00;

    for ($i = 0; $i < sizeof($rs2); ++$i) {
        // Articolo
        $body .= "<tr><td class='first_cell'>\n";
        $body .= '<span>'.$rs2[$i]['descrizione']."</span><br/>\n";
        $body .= '<span><small style="color:#777;">Intervento '.$rs2[$i]['idintervento'].' del '.Translator::dateToLocale($rs2[$i]['data_intervento'])."</small></span>\n";
        $body .= "</td>\n";

        // Quantità
        $qta = $rs2[$i]['qta'];
        $body .= "<td class='table_cell' align='center'>\n";
        $body .= '<span>'.Translator::numberToLocale($rs2[$i]['qta'], 2)."</span>\n";
        $body .= "</td>\n";

        // Prezzo unitario
        $body .= "<td class='table_cell' align='center'>\n";
        $netto = $rs2[$i]['prezzo'];
        $body .= '<span>'.Translator::numberToLocale($netto, 2)." &euro;</span>\n";
        $body .= "</td>\n";

        // Prezzo di vendita
        $body .= "<td class='table_cell' align='center'>\n";
        $body .= '<span>'.Translator::numberToLocale($netto * $qta, 2)." &euro;</span>\n";
        $body .= "</td></tr>\n";
        $totale_spese += $netto * $qta;
    }

    // Totale spese aggiuntive
    $body .= "<tr><td colspan=\"3\" align=\"right\">\n";
    $body .= "<b>ALTRE SPESE:</b>\n";
    $body .= "</td>\n";

    $body .= "<td align=\"center\" bgcolor=\"#dddddd\">\n";
    $body .= '<b>'.Translator::numberToLocale($totale_spese, 2)." &euro;</b>\n";
    $body .= "</td></tr>\n";
    $body .= "</table><br/>\n";
}

// Totale complessivo intervento
$body .= "<p align=\"right\">\n";
$body .= '<big><b>TOTALE INTERVENTI: '.Translator::numberToLocale($totale_intervento_scontato + $totale_articoli + $totale_spese, 2)." &euro;</b></big>\n";
$body .= "</p>\n";

$report_name = 'Riepilogo_interventi.pdf';
