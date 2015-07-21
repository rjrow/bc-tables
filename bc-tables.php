<?php
/**
 * Plugin Name: bc-tables
 * Plugin URI: local (or git later)
 * Description: Facilitates the access to the DB where bc data is stored and retrieved for the blue chips and job growth
 * Version: 1.0.0
 * Author: Robert Row
 * Text Domain: Optional. Plugin's text domain for localization. Example: mytextdomain
 * Domain Path: Optional. Plugin's relative directory path to .mo files. Example: /locale/
 * Network: Optional. Whether the plugin can only be activated network wide. Example: true
 * License: A short license name. Example: GPL2
 */
?>
<?php


function get_industry_list_by_area($area = 'Total Nonfarm')
{

    $area           = isset($_REQUEST['area']) ? $_REQUEST['area'] : $area ;
    $json_file      = file_get_contents(plugin_dir_path(__FILE__) . '/data/areas.json');
    $json_decoded   = json_decode($json_file, true);
    $json_industries     = $json_decoded[$area];
    if ($json_industries != NULL) {
        if ($_REQUEST['area']) {
            echo json_encode($json_industries);
            die();
        }else{
            return $json_industries;
        }
    } else {
        return 'Value is null - goood';
    }

}

add_action('wp_ajax_my_action', 'get_industry_list_by_area');
add_action('wp_ajax_nopriv_my_action', 'get_industry_list_by_area');


function table_populate($rows)
{
    foreach ($rows as $rows) {
        echo '<tr>';
        foreach ($rows as $key => $value) {
            echo '<td>', $value, '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
}

function clean_up_array($rows, $string_key)
{
    $new = array();
    foreach ($rows as $key => $value ) {
        array_push($new, $value[$string_key]);
    }
    return $new;
}


// Generate job growth tables shortcode function
function jg_table_gen($atts)
{
    ob_start();
    $table_type = isset($_POST['table_type']) ? $_POST['table_type'] : $atts['table_type'];
   	$form_controls =  isset($_POST['formcontrols']) ? false : true;

    // Setup databas e for call later on
    $DB_USER = DB_USER_jg;
    $DB_PASS = DB_PASS_jg;
    $DB_NAME = DB_NAME_jg;
    $DB_HOST = DB_HOST_jg;

    $newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);

    // Setup object for generating queries
    $tableQueries = Array(
        "RoMSAs" => Array(
            "table" => "msa_rankings",
            "table_us" => "national_rankings_t",
            "col_state_name" => "area_name"
        ),
        "CSR" => Array(
            "table" => "state_rankings",
            "table_us" => "national_rankings_t",
            "col_state_name" => "state_name"
        ),
        "ASR" => Array(
            "table" => "state_rankings",
            "table_us" => "national_rankings_t",
            "col_state_name" => "state_name"
        ),
        "MSAover" => Array(
            "table" => "msa_rankings_over",
            "table_us" => "national_rankings_t",
            "col_state_name" => "area_name"
        ),
        "MSAunder" => Array(
            "table" => "msa_rankings_under",
            "table_us" => "national_rankings_t",
            "col_state_name" => "area_name"
        ),
        "Historical" => Array(
            "table" => "state_rankings",
            "col_state_name" => "state_name"
        )
    );

    $colsQueries = Array(
        "yoy" => Array(
            "col_rank" => "rank",
            "col_pct_change" => "pct_change",
            "col_job_growth" => "job_growth",
            "col_value" => "value",
            "type_out" => "Year over year"
        ),
        "mom" => Array(
            "col_rank" => "rank_mom",
            "col_pct_change" => "pct_change_mom",
            "col_job_growth" => "job_growth_mom",
            "col_value" => "value_mom",
            "type_out" => "Month over month"
        ),
        "ytd" => Array(
            "col_rank" => "rank_ytd",
            "col_pct_change" => "pct_change_ytd",
            "col_job_growth" => "job_growth_ytd",
            "col_value" => "value_ytd",
            "type_out" => "Year to date"
        ),
        "ann" => Array(
            "col_rank" => "rank_ann",
            "col_pct_change" => "pct_change_ann",
            "col_job_growth" => "job_growth_ann",
            "col_value" => "value_ann_avg",
            "type_out" => "Annual"
        )
    );

    // Setting variables to be initialized to default settings if they are not selected
    $month      = !isset($_POST['month']) ? '1' : $_POST['month'];
    $year       = !isset($_POST['year']) ? date("Y") : $_POST['year'];
    $industry 	= !isset($_POST['industry']) ? 'Total Nonfarm' : $_POST['industry'];
    $area       = !isset($_POST['area']) ? 'Arizona' : $_POST['area'];
    $type       = !isset($_POST['type']) ? "yoy" : $_POST['type'];
    $msa_flag   = !isset($_POST['msa_flag']) ? "all" : $_POST['msa_flag'];


    $formValues = array(
    	'month' => $month,
    	'year' => $year,
    	'industry' => $industry,
    	'area' => $area,
    	'type' => $type,
    	'msa_flag' => $msa_flag
    );

    $types =  array(
       "ytd" => "Year to Date",
       "ann" => "Annual",
       "mom" => "Month over Month",
       "yoy" => "Year over Year"
    );


    $table          = $tableQueries[$table_type]['table'];
    $table_us       = $tableQueries[$table_type]['table_us'];
    $col_state_name = $tableQueries[$table_type]['col_state_name'];
    $col_rank       = $colsQueries[$type]['col_rank'];
    $col_pct_change = $colsQueries[$type]['col_pct_change'];
    $col_job_growth = $colsQueries[$type]['col_job_growth'];
    $col_value      = $colsQueries[$type]['col_value'];
    $type_out       = $colsQueries[$type]['type_out'];

    // Update Month so that we are pulling in correct data about current month (query table, date_ref_table)

    $sectors    = $newdb->get_results('SELECT DISTINCT industry_name FROM state_rankings;', ARRAY_A);
    $sectors    = clean_up_array($sectors, 'industry_name');

    $months = array("0" => "January",
        "1" => "February",
        "2" => "March",
        "3" => "April",
        "4" => "May",
        "5" => "June",
        "6" => "July",
        "7" => "August",
        "8" => "September",
        "9" => "October",
        "10" => "November",
        "11" => "December");


    $years      = $newdb->get_results('SELECT DISTINCT year FROM state_rankings;', ARRAY_A);
    $years      = clean_up_array($years, 'year');
    sort($years);

    switch ($table_type) {
        case "CSR":
            $month = date("n", strtotime($month));
            $rows = $newdb->get_results('SELECT ' . $col_state_name . ', ' . $col_rank . ', FORMAT(' . $col_pct_change . ',2),
							FORMAT(' . $col_job_growth . ',2), FORMAT(' . $col_value . ',2)
							FROM ' . $table . ' WHERE industry_name = "' . $industry . '"
							AND Year = "' . $year . '"
							AND Month = "' . $month . '"  UNION ALL
							      SELECT ' . $col_state_name . ', rank, FORMAT(' . $col_pct_change . ',2),
								FORMAT(' . $col_job_growth . ',2), FORMAT(' . $col_value . ',2)
								FROM ' . $table_us . ' WHERE industry_name = "' . $industry . '" AND industry_name <=> supersector_name
								AND Year = "' . $year . '"
								AND Month = "' . $month . '";');
            if ($form_controls):
           	?>
				<form method="post" class="bc-table" data-table-type="CSR">
					<div class="row">
						<div class="form-group col-xs-12 col-md-5">
							<?php populateDropDownControls('types', $types , $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-5">
							<?php populateDropDownControls('industry', $sectors, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<input name = "submit" type="submit" class="btn btn-primary" value = "Submit" />
						</div>
					</div>
				</form>
			<?php
			endif;
            break;
        case "ASR":
            $month = date("n", strtotime($month));
            $rows = $newdb->get_results('SELECT ' . $col_state_name . ', ' . $col_rank . ', FORMAT(' . $col_pct_change . ',2),
							FORMAT(' . $col_job_growth . ',2), FORMAT(' . $col_value . ',2)
							FROM ' . $table . ' WHERE industry_name = "' . $industry . '"
							AND Year = "' . $year . '"
							AND Month = "' . $month . '"  UNION ALL
							      SELECT ' . $col_state_name . ', rank, FORMAT(' . $col_pct_change . ',2),
								FORMAT(' . $col_job_growth . ',2), FORMAT(' . $col_value . ',2)
								FROM ' . $table_us . ' WHERE industry_name = "' . $industry . '" AND industry_name <=> supersector_name
								AND Year = "' . $year . '"
								AND Month = "' . $month . '";');
            if ($form_controls):
			?>
				<form method="post" class="bc-table" data-table-type="ASR">
					<div class="row">
                        <div class="form-group col-xs-12 col-md-3">
                            <?php populateDropDownControls('types', $types , $formValues); ?>
                        </div>
						<div class="form-group col-xs-12 col-md-3">
							<?php populateDropDownControls('industry', $sectors, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<?php populateDropDownControls('month', $months, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<?php populateDropDownControls('year', $years, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
						</div>
					</div>
				</form>
			<?php
			endif;
            break;
        case "RoMSAs":
            $month = date("n", strtotime($month));
            #if msa == over || msa == all || msa == under :: do stuff
            $rows = $newdb->get_results('SELECT ' . $col_state_name . ', ' . $col_rank . ', FORMAT(' . $col_pct_change . ',2), FORMAT(' . $col_job_growth . ',2),
							FORMAT(value,2)
							FROM ' . $table . ' WHERE industry_name = "' . $industry . '"
							AND Year = "' . $year . '"
							AND Month = "' . $month . '" ORDER BY ' . $col_rank . ';');

			if ($form_controls):
			?>
				<form method="post" class="bc-table" data-table-type="RoMSAs">
					<div class="row">
                        <div class="form-group col-xs-12 col-md-3">
                            <?php populateDropDownControls('types', $types , $formValues); ?>
                        </div>
						<div class="form-group col-xs-12 col-md-3">
							<?php populateDropDownControls('industry', $sectors, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<?php populateDropDownControls('month', $months, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<?php populateDropDownControls('year', $years, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
						</div>
					</div>
				</form>
			<?php
			endif;
            break;
        case "MSAover":
            $month = date("n", strtotime($month));
            $rows = $newdb->get_results('SELECT ' . $col_state_name . ', ' . $col_rank . ', FORMAT(' . $col_pct_change . ',2),
									FORMAT(' . $col_job_growth . ',2), FORMAT(' . $col_value . ',2)
									FROM ' . $table . ' WHERE industry_name = "' . $industry . '"
									AND Year = "' . $year . '"
									AND Month = "' . $month . '";');
			if ($form_controls):
			?>
				<form method="post" class="bc-table" data-table-type="MSAover">
					<div class="row">
						<div class="form-group col-xs-12 col-md-4">
							<?php populateDropDownControls('industry', $sectors, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-3">
							<?php populateDropDownControls('month', $months, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-3">
					 		<?php populateDropDownControls('year', $years, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
						</div>
					</div>
				</form>
			<?php
			endif;
            break;
        case "MSAunder":
            $month = date("n", strtotime($month));
            $rows = $newdb->get_results('SELECT ' . $col_state_name . ', ' . $col_rank . ', FORMAT(' . $col_pct_change . ',2),
							FORMAT(' . $col_job_growth . ',2), FORMAT(' . $col_value . ',2)
							FROM ' . $table . ' WHERE industry_name = "' . $industry . '"
							AND Year = "' . $year . '"
									AND Month = "' . $month . '";');
            if ($form_controls):
           	?>
				<form method="post" class="bc-table" data-table-type="MSAunder">
					<div class="row">
						<div class="form-group col-xs-12 col-md-4">
							<?php populateDropDownControls('industry', $sectors, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-3">
							<?php populateDropDownControls('month', $months, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-3">
							<?php populateDropDownControls('year', $years, $formValues); ?>
						</div>
						<div class="form-group col-xs-12 col-md-2">
							<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
						</div>
					</div>
				</form>
			<?php
			endif;
            break;
        case "Historical":
            $month = date("n", strtotime($month));
            $rows = $newdb->get_results('SELECT Year, ' . $col_rank . ', FORMAT(' . $col_pct_change . ',2),
	                        FORMAT(' . $col_job_growth . ',2), FORMAT(' . $col_value . ',2)
	                        FROM ' . $table . ' WHERE industry_name = "' . $industry . '"
	                        AND ' . $col_state_name . ' = "' . $area . '"
	                        AND Month = "' . $month . '" LIMIT 10000 OFFSET 2;');

           if ($form_controls) {
            	?>
				<form method="post" class="bc-table" data-table-type="Historical">
	            	 <div class="row">
	            	     <div class="form-group col-xs-12 col-md-4">
	             	        <select name = "area" class="form-control">
	        	<?php
                $newdb            = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);
                $fetch_state_name = $newdb->get_results('SELECT DISTINCT state_name FROM state_rankings ORDER BY state_name ASC;');
                echo '<optgroup label = "States">';
                if (!empty($fetch_state_name)): /** Loop through the $results and add each as a dropdown option */
                    foreach ($fetch_state_name as $result):
                        $options1 .= sprintf("\t" . '<option value="%1$s"' . ($formValues['area'] === $result->state_name ? ' selected ' : '' ) . '>%1$s</option>' . "\n", $result->state_name);
                    endforeach;
                    /** Output the dropdown */
                    echo $options1;
                    echo '</optgroup>';
                endif;

                $fetch_area_name = $newdb->get_results('SELECT DISTINCT area_name FROM msa_rankings  WHERE area_name LIKE "%,%" ORDER BY area_name ASC;');
                echo '<optgroup label = "MSAs">';
                if (!empty($fetch_area_name)): /** Loop through the $results and add each as a dropdown option */
                    foreach ($fetch_area_name as $result):
                        $options2 .= sprintf("\t" . '<option value="%1$s"' . ($formValues['area'] === $result->area_name ? ' selected ' : '' ) . '>%1$s</option>' . "\n", $result->area_name);
                    endforeach;
                    /** Output the dropdown */
                    echo $options2;
                endif;
              	echo '</optgroup>';
                echo '</select></div>';

                echo '<div class="form-group col-sx-12 col-md-3">';
                echo '<select name = "industry" id="select_industry" class="form-control">';
                $fetch_industries = get_industry_list_by_area($area);
                if($fetch_industries !== NULL){
                    foreach ($fetch_industries as $key => $value) {
                        echo '<option' . ($formValues['industry'] === $value ? ' selected ' : '' ) . '>' . $value. '</option>';
                    }
                }
                echo '</select>';
                echo '</div>';
                echo '<div class="form-group col-sx-12 col-md-3">';
                populateDropDownControls('month', $months, $formValues);
                echo '</div>';
                echo '<div class="form-group col-sm-12 col-md-2">';
                echo '<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>';
                echo '</div>';
                echo '</div>';
                echo '</form>';
            }
            break;
    }
    // Create tables which will be populated, only historical differs from the others hence the if statement
    if ($table_type == "Historical") {
        echo '<table class="table table-striped table-hover sortable" align = "center">
				<thead><tr>
					<th>State</th>
					<th>Rank</th>
					<th>% Change</th>
				    <th>Job Growth</th>
				    <th># of Jobs</th>
			     </tr></thead><tbody>';
    } else {
        echo '<table class="table table-striped table-hover sortable" align = "center">
			<thead><tr>
				<th>Year</th>
				<th>Rank</th>
				<th>% Change</th>
			    <th>Job Growth</th>
			    <th># of Jobs</th>
		        </tr></thead><tbody>';
    }

    table_populate($rows);

    $output = ob_get_clean();
    return $output;
}

//Generate wbc tables shortcode function
function bc_table_gen()
{

    ob_start();

    $form_controls =  isset($_POST['formcontrols']) ? false : true;
    $state       = !isset($_POST['state']) ? "Arizona" : $_POST['state'];

    $states = array(
    "arizona" => "Arizona",
    "california" => "California",
    "colorado" => "Colorado",
    "idaho" => "Idaho",
    "montana" => "Montana",
    "nevada" => "Nevada",
    "new mexico" => "New Mexico",
    "oregon" => "Oregon",
    "texas" => "Texas",
    "utah" => "Utah",
    "washington" => "Washington",
    "wyoming" => "Wyoming");

    $formValues = array(
        'state' => $states
        );


if($form_controls)
{
    ?>
                        <form method="post" class="wbc-table">
                        <div class="row">
                            <div class="form-group col-xs-12 col-md-4">
                                <?php populateDropDownControls('states', $states , $formValues); ?>
                            </div>
                            <div class="form-group col-xs-12 col-md-2">
                                <input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
                            </div>
                        </div>
                    </form>
    <?php
}


    //Grab years for table headers/captions
    $curr_year = date("Y");
    $next_year = $curr_year + 1;

    // Establish database connection for bc tables. bc tables are in a different db than jg. Different products.
    $DB_USER = DB_USER_wbc;
    $DB_PASS = DB_PASS_wbc;
    $DB_NAME = DB_NAME_wbc;
    $DB_HOST = DB_HOST_wbc;

    $newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);

    switch($state){
        case "nevada":
            $rows = $newdb->get_results('SELECT Organization, Q1A1, Q2A1_ggr, Q3A1,
    						 Q4A1, Q5A1
    						FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    						CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    						CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    						CASE WHEN Organization = "Old Consensus" THEN 0 END,
     						Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $curr_year . ' Forecasts Annual Percentage Change</caption>
    			  <thead>
                    <tr>
                      <th>&nbsp;</th>
        			  <th>Current $ Personal Income</th>
        			  <th>Gross Gaming Revenue</th>
        		      <th>Wage & Salary Employment</th>
        		      <th>Population Growth</th>
        		      <th>Single-Family Housing Permits</th>
    		        </tr>
                   </thead><tbody>';

            table_populate($rows);

            $rows = $newdb->get_results('SELECT Organization, Q1A2, Q2A2_ggr, Q3A2,
    						 Q4A2, Q5A2
    						FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    						CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    						CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    						CASE WHEN Organization = "Old Consensus" THEN 0 END,
     						Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $next_year . ' Forecasts Annual Percentage Change</caption>
    			  <thead><tr><th>&nbsp;</th>
    			  <th>Current $ Personal Income</th>
    			  <th>Gross Gaming Revenue</th>
    		      <th>Wage & Salary Employment</th>
    		      <th>Population Growth</th>
    		      <th>Single-Family Housing Permits</th>
    		       </tr></thead><tbody>';

            table_populate($rows);

        case "new mexico" :
        case "oregon"     :
            $rows = $newdb->get_results('SELECT Organization, Q1A1, Q2A1_mfg, Q3A1, Q4A1, Q5A1
    					FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    					CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    					CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    					CASE WHEN Organization = "Old Consensus" THEN 0 END,
    						Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $curr_year . ' Forecasts Annual Percentage Change<caption>
    			  <thead><tr><th>&nbsp;</th>
    			  <th>Current $ Personal Income</th>
    			  <th> Manufacturing Employment</th>
    		      <th>Wage & Salary Employment</th>
    		      <th>Population Growth</th>
    		      <th>Single-Family Housing Permits</th>
    		      </tr></thead><tbody>';

            table_populate($rows);

            $rows = $newdb->get_results('SELECT Organization, Q1A2, Q2A2_mfg, Q3A2, Q4A2, Q5A2
    						FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    						CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    						CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    						CASE WHEN Organization = "Old Consensus" THEN 0 END,
     						Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $next_year . ' Forecasts Annual Percentage Change</caption>
    			  <thead><tr><th>&nbsp;</th>
    			  <th>Current $ Personal Income</th>
    			  <th> Manufacturing Employment</th>
    		      <th>Wage & Salary Employment</th>
    		      <th>Population Growth</th>
    		      <th>Single-Family Housing Permits</th>
    		       </tr></thead><tbody>';

            table_populate($rows);

        case "montana":
            $rows = $newdb->get_results('SELECT Organization,Q1A1, Q3A1, Q4A1, Q5A1
    				FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    				CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    				CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    				CASE WHEN Organization = "Old Consensus" THEN 0 END,
    				Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $curr_year . ' Forecasts Annual Percentage Change</caption>
    			  <thead><tr><th>&nbsp;</th>
    			  <th>Current $ Personal Income</th>
    		      <th>Wage & Salary Employment</th>
    		      <th>Population Growth</th>
    		      <th>Single-Family Housing Permits</th>
    		      </tr></thead><tbody>';

            table_populate($rows);

            $rows = $newdb->get_results('SELECT Organization, Q1A2, Q3A2, Q4A2, Q5A2
    				FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    				CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    				CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    				CASE WHEN Organization = "Old Consensus" THEN 0 END,
    				Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $next_year . ' Forecasts Annual Percentage Change</caption>
    			  <thead><tr><th>&nbsp;</th>
    			  <th>Current $ Personal Income</th>
    		      <th>Wage & Salary Employment</th>
    		      <th>Population Growth</th>
    		      <th>Single-Family Housing Permits</th>
    		       </tr></thead><tbody>';

            table_populate($rows);

        default:
            $rows = $newdb->get_results('SELECT Organization, Q1A1, Q2A1, Q3A1, Q4A1, Q5A1
    				FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    				CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    				CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    				CASE WHEN Organization = "Old Consensus" THEN 0 END,
    				Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $curr_year . ' Forecasts Annual Percentage Change</caption>
    			  <thead><tr>
    			  <th>&nbsp;</th>
    			  <th>Current $ Personal Income</th>
    			  <th>Retail Sales</th>
    		      <th>Wage & Salary Employment</th>
    		      <th>Population Growth</th>
    		      <th>Single-Family Housing Permits</th>
    	          </tr></thead><tbody>';

            table_populate($rows);

            $rows = $newdb->get_results('SELECT Organization, Q1A2, Q2A2, Q3A2, Q4A2, Q5A2
    						FROM wbc_deployment WHERE States = "' . $state . '" AND Organization != "Old Consensus" ORDER BY
    						CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
    						CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
    	 					Organization ASC;');

            echo '<table class="table table-striped table-hover sortable">
    			  <caption> ' . $next_year . ' Forecasts Annual Percentage Change</caption>
    			  <thead><tr><th>&nbsp;</th>
    			  <th>Current $ Personal Income</th>
    			  <th>Retail Sales</th>
    		      <th>Wage & Salary Employment</th>
    		      <th>Population Growth</th>
    		      <th>Single-Family Housing Permits</th>
    	          </tr></thead><tbody>';

            table_populate($rows);
        }

    $output = ob_get_clean();
    return $output;
}

function gpbc_table_gen($atts)
{
    ob_start();

    $table_type = $atts['table_type'];

    // DB Connection
    $DB_USER = DB_USER_wbc;
    $DB_PASS = DB_PASS_wbc;
    $DB_NAME = DB_NAME_wbc;
    $DB_HOST = DB_HOST_wbc;

    $newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);
    //Economic forecast
    if ($table_type == "economic") {
        $rows = $newdb->get_results('SELECT organization,
										FORMAT(Q1,1),
										FORMAT(Q2,1),
										FORMAT(Q3,1),
										FORMAT(Q4,1),
										FORMAT(Q5,1),
										FORMAT(Q6,1)  FROM gpbc_deployment
										WHERE  year = "2015";');

        echo '<p align = "left"><b>First Quarter, 2015</b><p>';

        echo '<table class="table table-striped table-hover sortable">
		<caption>' . $curr_year . ' Forecast Annual Percentage Change</c
		<thead><tr>
              <th>&nbsp;</th>
			  <th>Population</th>
			  <th>Current $ Personal Income</th>
			  <th>Retail Sales</th>
		      <th>Wage & Salary Empl.</th>
		      <th>Manufacturing Empl.</th>
		      <th>Construction Empl.</th>
	    </tr></thead><tbody>';


        table_populate($rows);


        $rows = $newdb->get_results('SELECT organization,
										FORMAT(Q1,1),
										FORMAT(Q2,1),
										FORMAT(Q3,1),
										FORMAT(Q4,1),
										FORMAT(Q5,1),
										FORMAT(Q6,1)  FROM gpbc_deployment
										WHERE  year = "2016";');

        echo '<table class="table table-striped table-hover sortable">
		<caption>' . $next_year . ' Forecast Annual Percentage Change</c
		<thead><tr><th>&nbsp;</th>
			  <th>Population</th>
			  <th>Current $ Personal Income</th>
			  <th>Retail Sales</th>
		      <th>Wage & Salary Empl.</th>
		      <th>Manufacturing Empl.</th>
		      <th>Construction Empl.</th>
	              </tr></thead><tbody>';

        table_populate($rows);

    }


    //Construction forecast
    if ($table_type == "office") {
        $rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_office where year = "2015"');

        echo '<table class= table table-striped table-hover sortable">
			<caption>' . $curr_year . ' Office Forecast </caption>
			<thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';

        table_populate($rows);

        $rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_office where year = "2016"');

        echo '<table class="table table-striped table-hover sortable">
			<caption>' . $next_year . ' Office Forecast </caption>
			<thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';

        table_populate($rows);

    }


    if ($table_type == "residential") {
        $rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																 FORMAT(Q3,1),
																 FORMAT(Q4,1) from gpbc_residential where year = "2015"');

        echo '<table class="table table-striped table-hover sortable">
				<caption>' . $curr_year . ' Residential Forecast </cap
				<thead><tr>
					  <th>Organization</th>
					  <th>Single-family permits</th>
				      <th>Multi-family permits</th>
				      <th>Apartment Vacancy (Q4 %)</th>
				      <th>Apartment Absorpotion</th>
			              </tr></thead><tbody>';
        table_populate($rows);

        $rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																 FORMAT(Q3,1),
																 FORMAT(Q4,1) from gpbc_residential where year = "2016"');

        echo '<table class="table table-striped table-hover sortable">
				<caption>' . $next_year . ' Residential Forecast </cap
				<thead><tr>
					  <th>Organization</th>
					  <th>Single-family permits</th>
				      <th>Multi-family permits</th>
				      <th>Apartment Vacancy (Q4 %)</th>
				      <th>Apartment Absorpotion</th>
			              </tr></thead><tbody>';
        table_populate($rows);

    }


    if ($table_type == "industrial") {
        $rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_industrial where year = "2015"');

        echo '<table class="table table-striped table-hover sortable">
			  <caption>' . $curr_year . ' Industrial Forecast </caption>
			  <thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';
        table_populate($rows);

        $rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_industrial where year = "2016"');

        echo '<table class="table table-striped table-hover sortable">
			  <caption>' . $next_year . ' Industrial Forecast </caption>
			  <thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';
        table_populate($rows);
    }


    if ($table_type == "historical") {
        $rows = $newdb->get_results('SELECT row_type, Q1, Q2, Q3, Q4, Q5, Q6, Q7 FROM gpbc_historical');

        echo '<table class="table table-striped table-hover sortable">
			  <thead><tr><th>&nbsp;</th>
			  <th>Population (thousands)</th>
			  <th>Personal Income ($ millions)</th>
			  <th>Retail Sales ($ millions)</th>
			  <th>Wage & Salary Employment (thousands)</th>
			  <th>Manufacturing Employment (thousands)</th>
			  <th>Construction Employment (thousands)</th>
			  <th>Unemployment Rate</th></tr></thead><tbody>';

        table_populate($rows);
    }

    $output = ob_get_clean();
    return $output;
}

add_shortcode('jg-tables', 'jg_table_gen');
add_shortcode('bc-tables', 'bc_table_gen');
add_shortcode('gpbc-tables', 'gpbc_table_gen');

function populateDropDownControls($name, $dropdown_query, $formValues)
{
    $dropdown_complete = '<select name="' . $name . '" id = "' . $name . '" class="form-control">';
    foreach ($dropdown_query as $value) {
        $dropdown_complete .= '<option value ="' . $value . '"' . ($formValues[$name] === $value ? ' selected ' : '' ) . '>' . $value . '</option>';
    }
    $dropdown_complete .= '</select>';
    echo $dropdown_complete;
}


function echo_jg_table_gen()
{
    $custom_args = array(
        'table_type' => $_POST['table_type'],
        'area' => $_POST['area'],
        'industry' => $_POST['industry'],
        'month' => $_POST['month']
    );
    echo jg_table_gen($custom_args);
}

function echo_bc_table_gen()
{
    $custom_args = array(
        'state' => $_POST['state']);
    echo bc_table_gen($custom_args);
}

add_action('wp_ajax_echo_jg_table_gen', 'echo_jg_table_gen');
add_action('wp_ajax_nopriv_echo_jg_table_gen', 'echo_jg_table_gen');

add_action('wp_ajax_echo_bc_table_gen', 'echo_bc_table_gen');
add_action('wp_ajax_nopriv_echo_bc_table_gen', 'echo_bc_table_gen');


function bctables_js_enqueue()
{
    echo '<script type="text/javascript">var ajaxurl = "' . admin_url('admin-ajax.php') . '";</script>';
    wp_enqueue_media();
    // Registers and enqueues the required javascript.
    wp_register_script('bctables-js', plugin_dir_url(__FILE__) . 'js/jquery.form.js', array(
        'jquery'
    ));
    wp_enqueue_script('bctables-js');
}

add_action('wp_enqueue_scripts', 'bctables_js_enqueue');