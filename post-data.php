<?php
echo 'Job Sector: ' . $_POST['job_sector'] . '<br>';
echo 'Month: ' . $_POST['Month'] . '<br>';
echo 'Year: ' . $_POST['Year'];

$table_type = $_POST['table_type'];

// Establish database connection for bc tables. bc tables are in a different db than jg. Different products.
	$$DB_USER = DB_USER_jg;
	$DB_PASS = DB_PASS_jg;
	$DB_NAME = DB_NAME_jg;
	$DB_HOST = DB_HOST_jg;

	$newdb = new wpdb($DB_USER, $DB_PASS, $DB_NAME, $DB_HOST);

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
}
tablePopulate($rows);
?>