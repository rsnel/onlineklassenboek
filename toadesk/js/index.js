$(function(){
	var lokalen = [ [ 1, "115" ], [ 2, "113" ], [ 3, "110" ], [ 4, "122" ], [ 5, "123" ], [ 6, "125" ], [ 7, "127" ] ];
	var i;
	var tbody = $('#agenda > tbody');
	tbody.append('<tr><th>&nbsp;</th></tr>');
	$('#agenda > colgroup:eq(1)').attr('span', 7);
	$('#agenda > colgroup:eq(1)').attr('width', 98/7 + '%');
	for (i = 1; i <= 9; i++) {
		tbody.append('<tr><td>' + i + '</td></tr>');
		$('select[name=uur]', '#dialog').append('<option>' + i + '</option>');
	}
	for (i in lokalen) {
		$('> :eq(0)', tbody).append('<th>' + lokalen[i][1] + '</th>');
		$('select[name=lokaal_id]', '#dialog').append('<option value="' + lokalen[i][0] + '>' + lokalen[i][1] + '</option>');
		$('> :gt(0)', tbody).append('<td><div><div class="sl"><a href="#">+</a></div></div></td>');
	}

	$('#date').datepicker({
		showWeek: true,
		beforeShowDay: $.datepicker.noWeekends,
		firstDay: 1
	});

	$('.sl > a').click(function() {
		var dialog = $('#dialog').clone();
		var td = $(this).parent().parent().parent();
		var lokaal = td.index() - 1;
		var uur = td.parent().index() - 1;
		$('select[name=uur] > option:eq(' + uur + ')', dialog).attr('selected', 'selected');
		$('select[name=lokaal_id] > option:eq(' + lokaal + ')', dialog).attr('selected', 'selected');
		dialog.attr('title', 'Nieuwe notitie maken');
		dialog.dialog({
			buttons: {
				'Annuleren': function() {
					$(this).dialog('destroy');
				},
				'Opslaan': function() {
					var neu = $('<div>' + $('<div/>').text($('textarea', this).val()).html() + '</div>');
					var lokaal = $('select[name=lokaal_id] > option:selected', this).index() + 1;
					var uur = $('select[name=uur] > option:selected', this).index() + 1;
					$('> :eq(' + uur + ') > :eq(' + lokaal + ') > div', tbody).prepend(neu);
					$(this).dialog('destroy');
					neu.data('inuse', false);
					
					neu.dblclick(function() {
						if ($(this).data('inuse')) {
							alert('je bent deze notitie al aan het wijzigen');
							return;
						}
						$(this).data('inuse', true);

						var dialog = $('#dialog').clone();
						dialog.data('parent', this);
						var td = $(this).parent().parent();
						var lokaal = td.index() - 1;
						var uur = td.parent().index() - 1;
						$('select[name=uur] > option:eq(' + uur + ')', dialog).attr('selected', 'selected');
						$('select[name=lokaal_id] > option:eq(' + lokaal + ')', dialog).attr('selected', 'selected');
						$('textarea', dialog).text($(this).text());
						dialog.attr('title', 'Notitie wijzigen');
						dialog.dialog({
							buttons: {
								'Annuleren': function() {
									$(this).dialog('close');
									$(this).dialog('destroy');
								},
								'Opslaan': function() {
									var neu = $($(this).data('parent'));
									var nieuw_lokaal = $('select[name=lokaal_id] > option:selected', this).index() + 1;
									var nieuw_uur = $('select[name=uur] > option:selected', this).index() + 1;
									if (nieuw_lokaal != lokaal || nieuw_uur != nieuw_uur) {
										neu.detach();
										$('> :eq(' + nieuw_uur + ') > :eq(' + nieuw_lokaal + ') > div', tbody).prepend(neu);
									}
									$($(this).data('parent')).text($('textarea', this).val()).html();
									$(this).dialog('close');
									$(this).dialog('destroy');
								}
							},
							close: function () { $($(this).data('parent')).data('inuse', false) }
						});
						$('textarea', dialog).focus();
					});
                                 }
			}
		});

		$('textarea', dialog).focus();
		return false;
	});
});
