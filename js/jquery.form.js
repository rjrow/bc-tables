$(document).ready(function() {
  if ($('.navbar-form')) {

    var area_is_set = false;
    var area_set = document.getElementsByName('arealist')[0].value;

    if (area_set !== "") {
      area_is_set = true;
      console.log('area_is_set: ' + area_is_set);
      get_industry_list(document.getElementsByName("arealist"), area_is_set);
    }

    $('.navbar-form select[name=arealist]').change(function() {
      get_industry_list(this, true);
    });

  }

  function get_industry_list(select_option, area_is_set) {

    var selected_area;
    var area_set = $(select_option).val();
    if(area_set.includes("MSAs")){
      area_set = area_set.match(/[^[\]]+(?=])/g);
    }
    area_set = String(area_set);
    console.log('area_set: ' + area_set);
    selected_area = area_set;
    $('#select_industry').html('<option>Loading</option>');

    $.ajax({
      dataType: 'json',
      url: ajaxurl,
      data: {
        'action': 'my_action',
        'area': selected_area
      },
      success: function(data) {
        var selectIndustry = $('#select_industry');
        selectIndustry.html('');
        $.each(data, function(index, value){
          selectIndustry.append('<option>' + data[index] + '</option>');
        });
        $('#select_industry').show();
      },
      error: function(errorThrown) {
        alert('error');
        console.log(errorThrown);
      }
    });
  }
});
