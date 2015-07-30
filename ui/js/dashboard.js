(function($) {
	$('#game-date').hide();
	$('#game-score').hide();
	$('#gameEditModal').on('hidden.bs.modal', function(evt) {
		$('#game-date').hide();
		$('#game-score').hide();		
	});

	$('.btn-primary', '#gameEditModal').on('click', function(evt) {
		$('form', '#gameEditModal').submit();
	});
	
	$('#gameGameDate').datetimepicker();
	
	$('.modal-trigger').on('click', function(evt) {
		
		$this = $(this);
		data_url = $this.attr('data-url');
		data_attr = $this.attr('data-gameattr');
		data_target = $this.attr('data-target');
		
		debugger;
		$.ajax({
			url : data_url,
			cache : false,
			dataType : 'json',
			success : function(data, txtStatus, o) {
				debugger;
				$('#eid').val(data.eid);
				$('#section').val(data_attr);
				$('#redirectTo').val(window.location.href);
				if (data_attr == "game-score") {
					$('LABEL[for="visitingScore"]').html(data.visiting_team);
					$('LABEL[for="hostScore"]').html(data.host_team);
					
					$('#visitingScore').val(data.visiting_score);
					$('#hostScore').val(data.host_score);
				} else if (data_attr == "game-date") {
					game_date = moment(data.game_date_moment);
					dtp = $('#gameGameDate').data("DateTimePicker").defaultDate(game_date);
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
	
	$('.typeahead').typeahead({
		minLength	: 2,
		source: function(q, process) {
			return $.getJSON(
				'/team-typeahead/' + q,
				function (data) {
					return process(data);
				});
		}
	});
})(jQuery);