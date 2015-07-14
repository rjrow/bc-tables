$(document).ready(function() {
  if ($('.bc-table')) {

    var area_is_set = false;
    var area_set = document.getElementsByName('area')[0].value;

    if (area_set !== "") {
      area_is_set = true;
      console.log('area_is_set: ' + area_is_set);
      get_industry_list(document.getElementsByName("area"), area_is_set);
    }

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

    var selected_area;
    var area_set = $(select_option).val();
    var selectIndustry = $('#select_industry');

    console.log('area_set: ' + area_set);
    selected_area = area_set;
    selectIndustry.html('<option>Loading</option>');

    $.ajax({
      dataType: 'json',
      url: ajaxurl,
      data: {
        'action': 'my_action',
        'area': selected_area
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
