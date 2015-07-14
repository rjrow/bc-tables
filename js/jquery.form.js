$(document).ready(function() {
  if ($('.bc-table')) {

    $('.bc-table select[name=area]').change(function() {
      get_industry_list(this, true);
    });

    $('.bc-table').submit(function(event){
      event.preventDefault();
      var tableType = $(this).attr('data-table-type');
      get_new_table(this, tableType);
    });

  }

  function get_new_table(tableForm, tableType) {
    //loading
    $table = $(tableForm).siblings('table').html('<tr><td style="text-align: center; padding: 20px;"><i class="fa fa-2x fa-refresh fa-spin"></i></td></tr>');

    var formData = {
        'action'        : 'echo_jg_table_gen',
        'table_type'    : tableType,
        'formcontrols'  : false,
        'area'          : $('select[name=area]', tableForm).val(),
        'industry'      : $('select[name=industry]', tableForm).val(),
        'month'         : $('select[name=month]', tableForm).val()
    };

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: formData,
      success: function(data) {
        $table.replaceWith(data);
      },
      error: function(errorThrown) {
        alert('error');
        console.log(errorThrown);
      }
    });
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
        'area': area_set
      },
      success: function(data) {
        var defaultArea = 'Total Nonfarm';
        selectIndustry.html('');
        $.each(data, function(index, value){
          selectIndustry.append('<option' + (data[index] === defaultArea ? ' selected ' : '') + '>' + data[index] + '</option>');
        });
        selectIndustry.show();
      },
      error: function(errorThrown) {
        alert('error');
        console.log(errorThrown);
      }
    });
  }
});
