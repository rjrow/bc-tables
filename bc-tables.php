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

function get_industry_list_by_area(){
		if ( isset($_REQUEST) ) {
			$area = $_REQUEST['area'];
			$name = plugin_dir_path(__FILE__).'/data/areas.json';
			$json = file_get_contents($name);
			$json =	json_decode($json, true);
			$json_final = $json[$area];
			if ($json!=NULL){
				echo json_encode($json_final);
			}else{
				echo 'Value is null';
			}
		}
		die();
}
add_action( 'wp_ajax_my_action', 'get_industry_list_by_area' );
add_action('wp_ajax_nopriv_my_action', 'get_industry_list_by_area' );


function tablePopulate($rows){
	foreach ( $rows as $rows )
	{
	echo '<tr>';
	foreach($rows as $key=>$value)
	{
	echo '<td>',$value,'</td>';
	}
	echo '</tr>';
	}
	echo '</tbody></table>';
}

function createArray($rows, $string_key){
	$new_array = [];
	foreach ($rows as $rows)
	{
		foreach($rows as $key=>$value)
		{
		array_push($new_array, $value[$string_key]);
		}
	}
	return($new_array);
}

// Generate job growth tables shortcode function
function jg_table_gen($atts){
	ob_start();

	$table_type = $atts['table_type'];


	// Setup databas e for call later on
	$DB_USER = DB_USER_jg;
	$DB_PASS = DB_PASS_jg;
	$DB_NAME = DB_NAME_jg;
	$DB_HOST = DB_HOST_jg;

	$newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);


	// Setup object for generating queries
	$tableQueries = [
	"RoMSAs" => ["table" => "msa_rankings",
				 "table_us" => "national_rankings_t",
				 "col_state_name" => "area_name"],
	"CSR"    => ["table" => "state_rankings",
				 "table_us" => "national_rankings_t",
				 "col_state_name" => "state_name"],
	"ASR"	 => ["table" => "state_rankings",
				 "table_us" => "national_rankings_t",
				 "col_state_name" => "state_name"],
	"MSAover" => ["table" => "msa_rankings_over",
				 "table_us" => "national_rankings_t",
				 "col_state_name" => "area_name"],
	"MSAunder" => ["table" => "msa_rankings_under",
					"table_us" => "national_rankings_t",
					"col_state_name" => "area_name"],
	"Historical" => ["table" => "state_rankings",
					 "col_state_name" => "state_name"]
	];

	$colsQueries = [
		"yoy" => [
		"col_rank"		 => "rank",
		"col_pct_change" => "pct_change",
		"col_job_growth" => "job_growth",
		"col_value"		 => "value",
		"type_out"		 => "Year over year"
		],

		"mom" => [
		"col_rank"		 => "rank_mom",
		"col_pct_change" => "pct_change_mom",
		"col_job_growth" => "job_growth_mom",
		"col_value"		 => "value_mom",
		"type_out" 		 => "Month over month"
		],

		"ytd" => [
		"col_rank"		 => "rank_ytd",
		"col_pct_change" => "pct_change_ytd",
		"col_job_growth" => "job_growth_ytd",
		"col_value"		 => "value_ytd",
		"type_out" 		 => "Year to date"
		],

		"ann" => [
		"col_rank"       => "rank_ann",
		"col_pct_change" => "pct_change_ann",
		"col_job_growth" => "job_growth_ann",
		"col_value"		 => "value_ann_avg",
		"type_out" 		 => "Annual"
		],
	];


	// Setting variables to be initialized to default settings if they are not selected
	$Month = !isset($_POST['Month']) ? date("M") : $_POST['Month'];
	$Year  = !isset($_POST['Year']) ? date("Y") : $_POST['Year'];
	$job_sector = !isset($_POST['job_sector']) ? 'Total Nonfarm' : $_POST['job_sector'];
	$area  = !isset($_POST['area']) ? 'Arizona' : $_POST['area'];
	$type = !isset($_POST['type']) ? "yoy" : $_POST['type'];
	$msa_flag = !isset($_POST['msa_flag']) ? "all" : $_POST['msa_flag'];


	$table 			= $tableQueries[$table_type]['table'];
	$table_us 		= $tableQueries[$table_type]['table_us'];
	$col_state_name = $tableQueries[$table_type]['col_state_name'];
	$col_rank 		= $colsQueries[$type]['col_rank'];
	$col_pct_change = $colsQueries[$type]['col_pct_change'];
	$col_job_growth = $colsQueries[$type]['col_job_growth'];
	$col_value 		= $colsQueries[$type]['col_value'];
	$type_out		= $colsQueries[$type]['type_out'];


	// Update Month so that we are pulling in correct data about current month (query table, date_ref_table)
	$Month = "1";
	$MonthName	= date("F", strtotime($Month));

	$sectors = $newdb->get_results('SELECT DISTINCT industry_name FROM state_rankings;',ARRAY_A);
	$sector_array = array( "job_sector" => $sectors);
	$sector_array = createArray($sector_array, 'industry_name');

	$Months   = $newdb->get_results('SELECT DISTINCT Month FROM state_rankings;', ARRAY_A);
	sort($Months);
	$Month_array = array("Month" => $Months);
	$Month_array = createArray($Month_array, 'Month');

	$Years    = $newdb->get_results('SELECT DISTINCT year FROM state_rankings;', ARRAY_A);
	$Year_array = array("year" => $Years);
	$Year_array = createArray($Year_array, 'year');


	if($table_type == "CSR")
	{
		$rows = $newdb->get_results( 'SELECT '.$col_state_name.', '.$col_rank.', FORMAT('.$col_pct_change.',2),
						FORMAT('.$col_job_growth.',2), FORMAT('.$col_value.',2)
						FROM '.$table.' WHERE industry_name = "'.$job_sector.'"
						AND Year = "'.$Year.'"
						AND Month = "'.$Month.'"  UNION ALL
						      SELECT '.$col_state_name.', rank, FORMAT('.$col_pct_change.',2),
							FORMAT('.$col_job_growth.',2), FORMAT('.$col_value.',2)
							FROM '.$table_us.' WHERE industry_name = "'.$job_sector.'" AND industry_name <=> supersector_name
							AND Year = "'.$Year.'"
							AND Month = "'.$Month.'";');

		?>
		<form method="post" class="bc-table" data-type="jg_table_gen">
			<div class="row">
				<div class="col-xs-12 col-md-4">
					<?php echo populateDropDownControls('job_sector', $sector_array); ?>
				</div>
				<div class="col-xs-12 col-md-4">
					<input name = "submit" type="submit" class="btn btn-primary" value = "Submit" />
				</div>
			</div>
		</form>
		<?php
	}


	if($table_type == "ASR")
	{
		$rows = $newdb->get_results( 'SELECT '.$col_state_name.', '.$col_rank.', FORMAT('.$col_pct_change.',2),
						FORMAT('.$col_job_growth.',2), FORMAT('.$col_value.',2)
						FROM '.$table.' WHERE industry_name = "'.$job_sector.'"
						AND Year = "'.$Year.'"
						AND Month = "'.$Month.'"  UNION ALL
						      SELECT '.$col_state_name.', rank, FORMAT('.$col_pct_change.',2),
							FORMAT('.$col_job_growth.',2), FORMAT('.$col_value.',2)
							FROM '.$table_us.' WHERE industry_name = "'.$job_sector.'" AND industry_name <=> supersector_name
							AND Year = "'.$Year.'"
							AND Month = "'.$Month.'";');


		?>
		<form method="post" class="bc-table" data-type="jg_table_gen">
			<div class="row">
				<div class="col-xs-12 col-md-4">
					<?php populateDropDownControls('job_sector', $sector_array); ?>
				</div>
				<div class="col-xs-12 col-md-4">
					<?php populateDropDownControls('Month', $Month_array); ?>
				</div>
				<div class="col-xs-12 col-md-2">
					<?php populateDropDownControls('Year', $Year_array); ?>
				</div>
				<div class="col-xs-12 col-md-2">
					<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
				</div>
			</div>
		</form>
		<?php
	}


	if($table_type == "RoMSAs")
	{
		#if msa == over || msa == all || msa == under :: do stuff
		$rows = $newdb->get_results( 'SELECT '.$col_state_name.', '.$col_rank.', FORMAT('.$col_pct_change.',2), FORMAT('.$col_job_growth.',2),
						FORMAT(value,2)
						FROM '.$table.' WHERE industry_name = "'.$job_sector.'"
						AND Year = "'.$Year.'"
						AND Month = "'.$Month.'" ORDER BY '.$col_rank.';');

		?>
		<form method="post" class="bc-table" data-type="jg_table_gen">
			<div class="row">
				<div class="col-xs-12 col-md-4">
					<?php populateDropDownControls('job_sector', $sector_array); ?>
				</div>
				<div class="col-xs-12 col-md-2">
					<?php populateDropDownControls('Month', $Month_array); ?>
				</div>
				<div class="col-xs-12 col-md-2">
					<?php populateDropDownControls('Year', $Year_array); ?>
				</div>
				<div class="col-xs-12 col-md-2">
					<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
				</div>
			</div>
		</form>
		<?php
	}


	if($table_type == "MSAover")
	{
		$rows = $newdb->get_results('SELECT '.$col_state_name.', '.$col_rank.', FORMAT('.$col_pct_change.',2),
								FORMAT('.$col_job_growth.',2), FORMAT('.$col_value.',2)
								FROM '.$table.' WHERE industry_name = "'.$job_sector.'"
								AND Year = "'.$Year.'"
								AND Month = "'.$Month.'";');
								?>
		<form method="post" class="bc-table" data-type="jg_table_gen">
			<div class="row">
				<div class="col-xs-12 col-md-4">
					<?php populateDropDownControls('job_sector', $sector_array); ?>
				</div>
				<div class="col-xs-12 col-md-3">
					<?php populateDropDownControls('Month', $Month_array); ?>
				</div>
				<div class="col-xs-12 col-md-3">
			 		<?php populateDropDownControls('Year', $Year_array);?>
				</div>
				<div class="col-xs-12 col-md-2">
					<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
				</div>
			</div>
		</form>
	<?php
	}


	if($table_type == "MSAunder")
	{
		$rows = $newdb->get_results('SELECT '.$col_state_name.', '.$col_rank.', FORMAT('.$col_pct_change.',2),
						FORMAT('.$col_job_growth.',2), FORMAT('.$col_value.',2)
						FROM '.$table.' WHERE industry_name = "'.$job_sector.'"
						AND Year = "'.$Year.'"
								AND Month = "'.$Month.'";');

		?>
		<form method="post" class="bc-table" data-type="jg_table_gen">
			<div class="row">
				<div class="col-xs-12 col-md-4">
					<?php populateDropDownControls('job_sector', $sector_array); ?>
				</div>
				<div class="col-xs-12 col-md-3">
					<?php populateDropDownControls('Month', $Month_array); ?>
				</div>
				<div class="col-xs-12 col-md-3">
					<?php populateDropDownControls('Year', $Year_array); ?>
				</div>
				<div class="col-xs-12 col-md-2">
					<input name="submit" type="submit" class="btn btn-primary" value="Submit"/>
				</div>
			</div>
		</form>
		<?php 
	}


	if($table_type == "Historical")
	{
		$rows = $newdb->get_results('SELECT Year, '.$col_rank.', FORMAT('.$col_pct_change.',2),
						FORMAT('.$col_job_growth.',2), FORMAT('.$col_value.',2)
						FROM '.$table.' WHERE industry_name = "'.$job_sector.'"
						AND '.$col_state_name.' = "'.$area.'"
						AND Month = "'.$Month.'" LIMIT 10000 OFFSET 2;');
			?>
				<form method="post" class="bc-table" data-type="jg_table_gen">
					<div class="row"><div class="col-xs-12 col-md-4"><select name = "arealist" class="form-control">
			<?php

				$value = $_POST["arealist"];
				$value_area = $_POST["arealist"];

				$newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);
				$fetch_state_name = $newdb->get_results('SELECT DISTINCT state_name FROM state_rankings ORDER BY state_name ASC;');
				echo '<optgroup label = "States">';
				if(!empty($fetch_state_name)) :
			    /** Loop through the $results and add each as a dropdown option */
			    	foreach($fetch_state_name as $result) :
			        	$options1.= sprintf("\t".'<option value="%1$s">%1$s</option>'."\n", $result->state_name);
			    	endforeach;
			    	/** Output the dropdown */
			    	echo $options1;
				echo '</optgroup>';
					endif;

				$fetch_area_name = $newdb->get_results('SELECT DISTINCT area_name FROM msa_rankings  WHERE area_name LIKE "%,%" ORDER BY area_name ASC;');
				echo '<optgroup label = "MSAs">';
				if(!empty($fetch_area_name)) :
			    /** Loop through the $results and add each as a dropdown option */
			    	foreach($fetch_area_name as $result) :
			        	$options2.= sprintf("\t".'<option value="MSAs[%1$s]">%1$s</option>'."\n", $result->area_name);
			    	endforeach;
			    	/** Output the dropdown */
			    	echo $options2;
			    	echo '</optgroup>';
				echo '</select></div>';
						endif;

				echo '<div class="col-sx-12 col-md-3"><select name = "industrylist" id="select_industry" class="form-control">';
				    $value=$_POST["industrylist"];
				    $table_name = '';
				    $newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);
				echo '</select></div>';

				echo '<div class="col-sx-12 col-md-3"><select name="monthlist" class = "form-control">';
					$newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);
					$fetch_year_name = $newdb->get_results('SELECT DISTINCT CAST(Month AS UNSIGNED) AS Month FROM state_rankings ORDER BY CAST(Month AS UNSIGNED) ASC;');

					if(!empty($fetch_year_name)) :
				    /** Loop through the $results and add each as a dropdown option */
				    	$options = '';
				    	foreach($fetch_year_name as $result) :
							$MonthNum = $result->Month;
							$dateObj   = DateTime::createFromFormat('!m', $MonthNum);
							$MonthName = $dateObj->format('F');
				    		$options.= sprintf("\t".'<option value="%1$s">%1$s</option>'."\n", $MonthName);
				    	endforeach;
				    	/** Output the dropdown */
				    	echo $options;
				echo '</select></div>';
						endif;
				echo '<div class="col-sm-12 col-md-2"><input name="submit" type="submit" class="btn btn-primary" value="Submit"/></div></div>';
				echo '</form>';

	}


		// Create tables which will be populated, only historical differs from the others hence the if statement
		if($table_type == "Historical")
		{
			echo '	<table class= "table table-striped table-hover sortable" align = "center">
					<col span = "5"/>
					<thead><tr>
						<th>State</th>
						<th>Rank</th>
						<th>% Change</th>
					    <th>Job Growth</th>
					    <th># of Jobs</th>
				        </tr></thead><tbody>';
		}else
		{
			echo '	<table class= "table table-striped table-hover sortable" align = "center">
				<col span = "5"/>
				<thead><tr>
					<th>Year</th>
					<th>Rank</th>
					<th>% Change</th>
				    <th>Job Growth</th>
				    <th># of Jobs</th>
			        </tr></thead><tbody>';
		}

	tablePopulate($rows);

	$output = ob_get_clean();
	return $output;
}
add_action( 'wp_ajax_get_jg_table_gen', 'jg_table_gen' );
add_action('wp_ajax_nopriv_get_jg_table_gen', 'jg_table_gen' );


//Generate wbc tables shortcode function
function bc_table_gen($atts){
	ob_start();

	//Grab years for table headers/captions
	$curr_year = date("Y");
	$next_year = $curr_year + 1;

	$state = $atts['state'];
	$state = strtolower($state);

	// Establish database connection for bc tables. bc tables are in a different db than jg. Different products.
	$DB_USER = DB_USER_wbc;
	$DB_PASS = DB_PASS_wbc;
	$DB_NAME = DB_NAME_wbc;
	$DB_HOST = DB_HOST_wbc;

	$newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);


	if($state == "nevada")
	{
	$rows = $newdb->get_results( 'SELECT Organization, Q1A1, Q2A1_ggr, Q3A1,
					 Q4A1, Q5A1
					FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
					CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
					CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
					CASE WHEN Organization = "Old Consensus" THEN 0 END,
 					Organization ASC;');


		echo '<table class= "table table-striped table-hover sortable">
			  <caption> '.$curr_year.' Forecasts Annual Percentage Change</caption>
			  <col span = "5" />
			  <thead><tr><th></th>
			  <th>Current $ Personal Income</th>
			  <th>Gross Gaming Revenue</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);


	$rows = $newdb->get_results( 'SELECT Organization, Q1A2, Q2A2_ggr, Q3A2,
					 Q4A2, Q5A2
					FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
					CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
					CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
					CASE WHEN Organization = "Old Consensus" THEN 0 END,
 					Organization ASC;');

		echo '<table class= "table table-striped table-hover sortable">
			  <caption> '.$next_year.' Forecasts Annual Percentage Change</caption>
			  <col span = "5" />
			  <thead><tr><th></th>
			  <th>Current $ Personal Income</th>
			  <th>Gross Gaming Revenue</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);

	}




	if($state == "new mexico" OR $state == "oregon")
	{
	$rows = $newdb->get_results( 'SELECT Organization, Q1A1, Q2A1_mfg, Q3A1, Q4A1, Q5A1
				FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
				CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Old Consensus" THEN 0 END,
					Organization ASC;');

		echo '<table class= "table table-striped table-hover sortable">
			  <caption> '.$curr_year.' Forecasts Annual Percentage Change<caption>
			  <col span = "5" />
			  <thead><tr><th></th>
			  <th>Current $ Personal Income</th>
			  <th> Manufacturing Employment</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);


	$rows = $newdb->get_results( 'SELECT Organization, Q1A2, Q2A2_mfg, Q3A2, Q4A2, Q5A2
					FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
					CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
					CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
					CASE WHEN Organization = "Old Consensus" THEN 0 END,
 					Organization ASC;');

		echo '<table class= "table table-striped table-hover sortable">
			  <caption> '.$next_year.' Forecasts Annual Percentage Change</caption>
			  <col span = "5" />
			  <thead><tr><th></th>
			  <th>Current $ Personal Income</th>
			  <th> Manufacturing Employment</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);

	}




	if($state == "montana")
	{
	$rows = $newdb->get_results( 'SELECT Organization,Q1A1, Q3A1, Q4A1, Q5A1
				FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
				CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Old Consensus" THEN 0 END,
				Organization ASC;');

		echo '<table class="table table-striped table-hover sortable">
			  <caption> '.$curr_year.' Forecasts Annual Percentage Change</caption>
			  <col span = "5" />
			  <thead><tr><th></th>
			  <th>Current $ Personal Income</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);


	$rows = $newdb->get_results( 'SELECT Organization, Q1A2, Q3A2, Q4A2, Q5A2
				FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
				CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Old Consensus" THEN 0 END,
				Organization ASC;');

		echo '
			  <table class="table table-striped table-hover sortable">
			  <caption> '.$next_year.' Forecasts Annual Percentage Change</caption>
			  <col span = "5" />
			  <thead><tr><th></th>
			  <th>Current $ Personal Income</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);

	}



	else
	{
		$rows = $newdb->get_results( 'SELECT Organization, Q1A1, Q2A1, Q3A1, Q4A1, Q5A1
				FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
				CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
				CASE WHEN Organization = "Old Consensus" THEN 0 END,
				Organization ASC;');

		echo '<table class= "table table-striped table-hover sortable">
			  <col span = "5" />
			  <caption> '.$curr_year.' Forecasts Annual Percentage Change</caption>
			  <thead><tr>
			  <th></th>
			  <th>Current $ Personal Income</th>
			  <th>Retail Sales</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);


		$rows = $newdb->get_results( 'SELECT Organization, Q1A2, Q2A2, Q3A2, Q4A2, Q5A2
						FROM wbc_deployment WHERE States = "'.$state.'" AND Organization != "Old Consensus" ORDER BY
						CASE WHEN Organization = "Last Month Consensus" THEN 1 ELSE 0 END,
						CASE WHEN Organization = "Consensus" THEN 1 ELSE 0 END,
	 					Organization ASC;');

		echo '<table class="table table-striped table-hover sortable">
		      <col span = "5" />
			  <caption> '.$next_year.' Forecasts Annual Percentage Change</caption>
			  <thead><tr><th></th>
			  <th>Current $ Personal Income</th>
			  <th>Retail Sales</th>
		      <th>Wage & Salary Employment</th>
		      <th>Population Growth</th>
		      <th>Single-Family Housing Permits</th>
	          </tr></thead><tbody>';

		tablePopulate($rows);

	}

	$output = ob_get_clean();
	return $output;
}
add_action( 'wp_ajax_get_bc_table_gen', 'bc_table_gen' );
add_action('wp_ajax_nopriv_get_bc_table_gen', 'bc_table_gen' );

function gpbc_table_gen($atts){
	ob_start();

	$table_type = $atts['table_type'];

	// DB Connection
	$DB_USER = DB_USER_wbc;
	$DB_PASS = DB_PASS_wbc;
	$DB_NAME = DB_NAME_wbc;
	$DB_HOST = DB_HOST_wbc;

	$newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);
	//Economic forecast
	if($table_type == "economic")
	{
		$rows = $newdb->get_results('SELECT organization,
										FORMAT(Q1,1),
										FORMAT(Q2,1),
										FORMAT(Q3,1),
										FORMAT(Q4,1),
										FORMAT(Q5,1),
										FORMAT(Q6,1)  FROM gpbc_deployment
										WHERE  year = "2015";');

		echo '<p align = "left"><b>First Quarter, 2015</b><p>';

		echo '<table class= "table table-striped table-hover sortable">
		<caption>'.$curr_year.' Forecast Annual Percentage Change</caption>
		<col span = "5" />
		<thead><tr><th></th>
			  <th>Population</th>
			  <th>Current $ Personal Income</th>
			  <th>Retail Sales</th>
		      <th>Wage & Salary Empl.</th>
		      <th>Manufacturing Empl.</th>
		      <th>Construction Empl.</th>
	              </tr></thead><tbody>';


		tablePopulate($rows);


		$rows = $newdb->get_results('SELECT organization,
										FORMAT(Q1,1),
										FORMAT(Q2,1),
										FORMAT(Q3,1),
										FORMAT(Q4,1),
										FORMAT(Q5,1),
										FORMAT(Q6,1)  FROM gpbc_deployment
										WHERE  year = "2016";');

		echo '<table class= "table table-striped table-hover sortable">
		<caption>'.$next_year.' Forecast Annual Percentage Change</caption>
		<col span = "5" />
		<thead><tr><th></t>
			  <th>Population</th>
			  <th>Current $ Personal Income</th>
			  <th>Retail Sales</th>
		      <th>Wage & Salary Empl.</th>
		      <th>Manufacturing Empl.</th>
		      <th>Construction Empl.</th>
	              </tr></thead><tbody>';

		tablePopulate($rows);

	}


	//Construction forecast
	if($table_type == "office")
	{
		$rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_office where year = "2015"');

		echo '<table class= "table table-striped table-hover sortable">
			<caption>'.$curr_year.' Office Forecast </caption>
			<col span = "4" />
			<thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';

		tablePopulate($rows);

		$rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_office where year = "2016"');

		echo '<table class= "table table-striped table-hover sortable">
			<caption>'.$next_year.' Office Forecast </caption>
			<col span = "4" />
			<thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';

		tablePopulate($rows);

	}


	if($table_type == "residential")
	{
		$rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																 FORMAT(Q3,1),
																 FORMAT(Q4,1) from gpbc_residential where year = "2015"');

		echo '<table class= "table table-striped table-hover sortable">
				<caption>'.$curr_year.' Residential Forecast </caption>
				<col span = "5" />
				<thead><tr>
					  <th>Organization</th>
					  <th>Single-family permits</th>
				      <th>Multi-family permits</th>
				      <th>Apartment Vacancy (Q4 %)</th>
				      <th>Apartment Absorpotion</th>
			              </tr></thead><tbody>';
	    tablePopulate($rows);

		$rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																 FORMAT(Q3,1),
																 FORMAT(Q4,1) from gpbc_residential where year = "2016"');

		echo '<table class= "table table-striped table-hover sortable">
				<caption>'.$next_year.' Residential Forecast </caption>
				<col span = "5" />
				<thead><tr>
					  <th>Organization</th>
					  <th>Single-family permits</th>
				      <th>Multi-family permits</th>
				      <th>Apartment Vacancy (Q4 %)</th>
				      <th>Apartment Absorpotion</th>
			              </tr></thead><tbody>';
	    tablePopulate($rows);

	}


	if($table_type == "industrial")
	{
		$rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_industrial where year = "2015"');

		echo '<table class= "table table-striped table-hover sortable">
			  <caption>'.$curr_year.' Industrial Forecast </caption>
			  <col span = "4" />
			  <thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';
	   tablePopulate($rows);

	   $rows = $newdb->get_results('SELECT organization, FORMAT(Q1,1),
																FORMAT(Q2,1),
																FORMAT(Q3,1) from gpbc_industrial where year = "2016"');

		echo '<table class= "table table-striped table-hover sortable">
			  <caption>'.$next_year.' Industrial Forecast </caption>
			  <col span = "4" />
			  <thead><tr>
				  <th>Organization</th>
				  <th>Construction</th>
			      <th>Vacancy (Year End %)</th>
			      <th>Absorpotion</th>
		              </tr></thead><tbody>';
	   tablePopulate($rows);
	}

	$output = ob_get_clean();
	return $output;
}
add_action( 'wp_ajax_get_gpbc_table_gen', 'gpbc_table_gen' );
add_action('wp_ajax_nopriv_get_gpbc_table_gen', 'gpbc_table_gen' );

function populateDropDownControls($name, $dropdown_query){
	$dropdown_complete = '<select name="'.$name.'" id = "'.$name.'" class="form-control">';
	foreach ($dropdown_query as $value){
		$dropdown_complete .= '<option value ="'.$value.'">'.$value.'</option>';
	}
	$dropdown_complete .= '</select>';
	echo $dropdown_complete;
}

add_shortcode('jg-tables'  , 'jg_table_gen');
add_shortcode('bc-tables'  , 'bc_table_gen');
add_shortcode('gpbc-tables', 'gpbc_table_gen');

function bctables_js_enqueue() {
		echo '<script type="text/javascript">var ajaxurl = "'. admin_url('admin-ajax.php') .'";</script>';
        wp_enqueue_media();
        // Registers and enqueues the required javascript.
        wp_register_script( 'bctables-js', plugin_dir_url( __FILE__ ) . 'js/jquery.form.js', array( 'jquery' ) );
        wp_enqueue_script( 'bctables-js' );
}

add_action( 'wp_enqueue_scripts', 'bctables_js_enqueue' );