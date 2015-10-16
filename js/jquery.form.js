(function($) {

  $(document).ready(function() {

    if ($('.bc-table')) {
      var month = $("#selected_month").text();
      $("#month").val(month);

      $(document).on('change', '.bc-table select[name=area]', function(event) {
        get_industry_list(this, true);
      });

      $(document).on('change', '.bc-table select[name=types]', function(event) {
        check_ytd(this, true);
      });

      $(document).on("submit", ".bc-table", function(event) {
        event.preventDefault();
        var tableType = $(this).attr('data-table-type');
        get_new_table(this, tableType);
      });
    }
    var display = document.querySelector('#time'),
           timer = new CountDownTimer(34);

    timer.onTick(format).start();

    function format(minutes, seconds) {
      minutes = minutes < 10 ? "0" + minutes : minutes;
      seconds = seconds < 10 ? "0" + seconds : seconds;
      display.textContent = minutes + ':' + seconds;
    }

    function init_jg_table(tableType) {
      console.log('jelp');
      var formData = {
        'action': 'echo_jg_table_gen',
        'table_type': tableType,
      };
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        success: function(data) {
          $('table.loading').replaceWith(data);
          $(".sortable").tablesorter();
        },
        error: function(errorThrown) {
          alert('error');
          console.log(errorThrown);
        }
      });
    }
    if ($('.table.loading')) {
      var tableType = $('.table.loading').attr('data-table-type');
      init_jg_table(tableType);
    }

    if ($('.wbc-table')) {
      $('.wbc-table').submit(function(event) {
        event.preventDefault();
        get_wbc_table(this);
      });
    }

    function get_wbc_table(tableForm) {
      //loading
      $tableForm = $(tableForm);
      $tableForm.siblings('table').each(function() {
        $(this).hide().remove();
      });
      $tableForm.after('<table class="table loading table-stripped"><tr><td style="text-align: center; padding: 20px;"><i class="fa fa-2x fa-refresh fa-spin"></i></td></tr></table>');

      var formData = {
        'action': 'echo_bc_table_gen',
        'state': $('select[name=states]', tableForm).val(),
        'formcontrols': false
      };

      console.log(formData);
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        success: function(data) {
          //console.log(data);
          $('table.loading').replaceWith(data);
        },
        error: function(errorThrown) {
          alert('error');
          console.log(errorThrown);
        }
      });
    }

    function get_new_table(tableForm, tableType) {
      //loading
      $tableForm = $(tableForm);
      $tableForm.siblings('table').each(function() {
        $(this).hide().remove();
      });
      $tableForm.after('<table class="table loading table-stripped"><tr><td style="text-align: center; padding: 20px;"><i class="fa fa-2x fa-refresh fa-spin"></i><p>Please wait <span id="time"></span> seconds for data to load</p></td></tr></table>');
      var display = document.querySelector('#time'),
           timer = new CountDownTimer(0.5);

      timer.onTick(format).start();

      var formData = {
        'action': 'echo_jg_table_gen',
        'table_type': tableType,
        'formcontrols': false,
        'types': $('select[name=types]', tableForm).val(),
        'area': $('select[name=area]', tableForm).val(),
        'industry': $('select[name=industry]', tableForm).val(),
        'year': $('select[name=year]', tableForm).val(),
        'month': $('select[name=month]', tableForm).val()
      };

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        success: function(data) {
          $('table.loading').replaceWith(data);
          $(".sortable").tablesorter();
        },
        error: function(errorThrown) {
          alert('error');
          console.log(errorThrown);
        }
      });
    }

    function check_ytd(select_option, type_is_set) {
      var type = $(select_option).val();
      if (type == "ytd") {
        $('#month').hide();
      } else {
        $('#month').show();
      }
    }

    function get_industry_list(select_option, area_is_set) {

      var area_set = $(select_option).val();
      var selectIndustry = $('#select_industry');
      selectIndustry.html('<option>Loading</option>');

      console.log('area_set: ' + area_set);

      $.ajax({
        dataType: 'json',
        url: ajaxurl,
        data: {
          'action': 'my_action',
          'area': area_set,
        },
        success: function(data) {
          var defaultArea = 'Total Nonfarm';
          selectIndustry.html('');
          $.each(data, function(index, value) {
            selectIndustry.append('<option' + (data[index] === defaultArea ? ' selected ' : '') + '>' + data[index] + '</option>');
          });
          selectIndustry.show();
        },
        error: function(errorThrown) {
          console.log(errorThrown);
        }
      });
    }
  });
}(jQuery));
