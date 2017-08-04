<?php

include_once __DIR__.'/../../core.php';

unset($_SESSION['superselect']['id_categoria']);

?><form action="editor.php?id_module=$id_module$" method="post">
	<input type="hidden" name="op" value="add">
	<input type="hidden" name="backto" value="record-edit">

	<div class="row">

		<div class="col-md-6">
			{[ "type": "text", "label": "<?php echo _('Inserisci il codice:'); ?>", "name": "codice", "required": 1, "value": "" ]}
		</div>

		<div class="col-md-6">
			{[ "type": "text", "label": "<?php echo _('Inserisci la descrizione:'); ?>", "name": "descrizione", "required": 1, "value": "" ]}
		</div>

		<div class="col-md-6">
			{[ "type": "select", "label": "<?php echo _('Inserisci la categoria:'); ?>", "name": "categoria", "required": 1, "value": "", "ajax-source": "categorie", "icon-after": "add|<?php echo Modules::getModule('Categorie')['id']; ?>" ]}
		</div>

		<div class="col-md-6">
			{[ "type": "select", "label": "<?php echo _('Inserisci la subcategoria:'); ?>", "name": "subcategoria", "value": "", "ajax-source": "sottocategorie", "icon-after": "add|<?php echo Modules::getModule('Categorie')['id']; ?>||hide" ]}
		</div>

	</div>

	<!-- PULSANTI -->
	<div class="row">
		<div class="col-md-12 text-right">
			<button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> <?php echo _('Aggiungi'); ?></button>
		</div>
	</div>
</form>

<script>
var original = $('#subcategoria').parent().find(".input-group-addon button").data("href");

$('#categoria').change( function(){
	session_set('superselect,id_categoria', $(this).val(), 0);

    $("#subcategoria").selectReset();

	if($(this).val()){
		$("#subcategoria").parent().find(".input-group-addon button").removeClass("hide");
        $("#subcategoria").parent().find(".input-group-addon button").data("href", original + "&id_original="+$(this).val());
	}
	else {
		$("#subcategoria").parent().find(".input-group-addon button").addClass("hide");
	}
});
</script>
