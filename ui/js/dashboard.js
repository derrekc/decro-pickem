(function($) {
	// DOM elements to add/remove as needed
	
	$('#game-date').hide();
	$('#game-score').hide();
	
	$('#gameEditModal').on('hidden.bs.modal', function(evt) {
		
		$('#visitingScore').val('');
		$('#hostScore').val('');
		$('#gameGameDate').data("DateTimePicker").defaultDate('');
		$('#gameTV').val('');
		$('#gameTVSecondary').val('');
		
		$('#game-date').hide();
		$('#game-score').hide();		
	});

	$('.btn-primary', '#gameEditModal').on('click', function(evt) {
		$('form', '#gameEditModal').submit();
	});
	
	$('#gameGameDate').datetimepicker({sideBySide: true});
	$('#newGameGameDate').datetimepicker({sideBySide: true});
	
	$("[data-date-value]").each(function(n, e) {
		aMoment = moment($(e).attr('data-date-value'));
		$(e).parent('#gameGameDate').data('DateTimePicker').defaultDate(aMoment);
	});
	
	$('.modal-trigger').on('click', function(evt) {
		
		$this = $(this);
		data_url = $this.attr('data-url');
		data_attr = $this.attr('data-gameattr');
		data_target = $this.attr('data-target');
		
		$.ajax({
			url : data_url,
			cache : false,
			dataType : 'json',
			
			success : function(data, txtStatus, o) {
				$('#eid').val(data.eid);
				$('#section').val(data_attr);
				$('#redirectTo').val(window.location.href);
				
				if (data_attr == "game-score") {
					$('LABEL[for="visitingScore"]').html(data.visiting_team);
					$('LABEL[for="hostScore"]').html(data.host_team);
					
					$('#visitingScore').val(data.visiting_score);
					$('#hostScore').val(data.host_score);
					$('#overtimes').val(data.overtimes);
					
				} else if (data_attr == "game-date") {
					game_date = moment(data.game_date_moment);
					dtp = $('#gameGameDate').data("DateTimePicker").defaultDate(game_date);
					$('#gameEditModalLabel').html(data.modal_title);
					//$('#gameTV').val(data.game_tv);
					$('#gameTV option[value="' + data.game_tv + '"]').prop('selected', true);
					$('#gameTVSecondary').val(data.game_tv_seconary);
					
				}
				$(data_target).modal('show');
				$('#' + data_attr).show(); // Select tab by name
			},
			error : function(o, txtStatus, err) {
				debugger;
			}
		})
		return evt.preventDefault();
	});
	
	// TEAM TYPEAHEAD
	// --------------------------------------------------------------
	$('.team-typeahead').typeahead({
		minLength	: 2,
		items : 20,
		source: function(q, process) {
			var unix = Math.round(+new Date()/1000);
			return $.getJSON(
				'/team-typeahead/' + q + '?' + unix,
				function (data) {
					return process(data);
				});
		}
	}).change(function() {
		var current = $(this).typeahead('getActive');
		var data_target;
		var current_val = $(this).val();

    if (current) {
        // Some item from your model is active!
        if (current.name == $(this).val()) {
            // This means the exact match is found. Use toLowerCase() if you want case insensitive match.
					data_target = $(this).attr('data-target');
					$(data_target).val(current.machinename);

        } else {
            // This means it is only a partial match, you can either add a new item 
            // or take the active if you don't want new items
        }
    } else {
        // Nothing is active so it is a new value (or maybe empty value)
    }
	});
	
	
	// LOCATION TYPEAHEAD
	// --------------------------------------------------------------
	$('.location-typeahead').typeahead({
		minLength	: 2,
		items : 20,
		source: function(q, process) {
			var unix = Math.round(+new Date()/1000);
			return $.getJSON(
				'/gamelocation-typeahead/' + q + '?' + unix,
				function (data) {
					return process(data);
				});
		}
	}).change(function() {
		var current = $(this).typeahead('getActive');
		var data_target;
		var current_val = $(this).val();

    if (current) {
        // Some item from your model is active!
        if (current.name == $(this).val()) {
            // This means the exact match is found. Use toLowerCase() if you want case insensitive match.
					//data_target = $(this).attr('data-target');
					//$(data_target).val(current.machinename);

        } else {
            // This means it is only a partial match, you can either add a new item 
            // or take the active if you don't want new items
        }
    } else {
        // Nothing is active so it is a new value (or maybe empty value)
    }
	});
	
	$('a.toggleGameStatus').on('click', function(evt) {
		debugger;
		var gamestatus = $(this).attr('data-gamestatus'),
			  target_eid = $(this).attr('data-target-eid'),
			  $this = $(this);
			  
		$.ajax({
			url : '/admin/game/toggle-status',
			data : { eid : target_eid, hide_from_pickem : gamestatus },
			type : 'POST',
			dataType : 'json',
			success : function (data, txtStatus, o) {
				$this.parent('TD.status-aware').toggleClass('warning', gamestatus == 0);
				$('span[aria-hidden]', $this)
					.removeClass()
					.addClass(function() {
						icon = (gamestatus == 1) ? 'plus-sign' : 'remove-circle'
						return 'glyphicon glyphicon-' + icon;
					});
				label = (gamestatus == 1) ? 'Add to Pickem' : 'Remove from Pickem';
				$('span.link-title', $this).html( label );
			},
			error : function (o, status, err) {
				debugger;
			}
		})
	});
	
	//-------------- COLORBOX TRIGGERS ----------------//
	$('a.gallery').colorbox();
	
	//-------------- TEAM LOGOS as TRIGGERS -------------------//
	$('IMG[data-toggle="pickem"]').on('click', function(evt) {
		var target = $(this).attr('data-target');
		$(target + ' option[value="' + $(this).attr('data-teamname') + '"]').prop('selected', true);
	});
	
	//------------- TABLE BACKUP BUTTON and PROGRESS BAR -----------//
	$('.progress', '#collapseTableBackup').hide();
	$('#triggerTableBackup').on('click', function(evt) {
		$.ajax({
			url					: '/admin/pickem/backup',
			cache				: false,
			beforeSend	: function(o, settings) {
				$('.progress', '#collapseTableBackup').show();
			},
			success			: function(data, txtStatus, o) {
				$('.progress', '#collapseTableBackup').hide();
				alert('Table Backups have been created.');
			}
		});
		return evt.preventDefault();
	});
	
	//----------- GENeral MODAL triggers -------------
	$('.genmodal-trigger').on('click', function(evt) {
		data_url = $(this).attr('data-url');
		$.ajax({
			url : data_url,
			type : 'GET',
			success : function(data, status, o) {
				$('#genModal .modal-body').html(data.content);
				$('#genModal').modal('show');
			}
		});
		return evt.preventDefault();
	})
})(jQuery);