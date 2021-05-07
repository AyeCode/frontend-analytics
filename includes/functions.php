<?php


/**
 * Formats seconds into to h:m:s.
 *
 * @since 1.0.0
 *
 * @param int  $sec The number of seconds.
 * @param bool $padHours Whether add leading zero for less than 10 hours. Default false.
 * @return string h:m:s format.
 */
function frontend_analytics_sec2hms( $sec, $padHours = false ) {
    // holds formatted string
    $hms = "";
    // there are 3600 seconds in an hour, so if we
    // divide total seconds by 3600 and throw away
    // the remainder, we've got the number of hours
    $hours = intval(intval($sec) / 3600);

    // add to $hms, with a leading 0 if asked for
    $hms .= ($padHours) ? str_pad($hours, 2, "0", STR_PAD_LEFT) . ':' : $hours . ':';

    // dividing the total seconds by 60 will give us
    // the number of minutes, but we're interested in
    // minutes past the hour: to get that, we need to
    // divide by 60 again and keep the remainder
    $minutes = intval(($sec / 60) % 60);

    // then add to $hms (with a leading 0 if needed)
    $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT) . ':';

    // seconds are simple - just divide the total
    // seconds by 60 and keep the remainder
    $seconds = intval($sec % 60);

    // add to $hms, again with a leading 0 if needed
    $hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

    // done!
    return $hms;
}

/**
 * Get the google analytics via api.
 *
 * @since 1.0.0
 *
 * @param string $page Page url to use in analytics filters.
 * @param bool   $ga_start The start date of the data to include in YYYY-MM-DD format.
 * @param bool   $ga_end The end date of the data to include in YYYY-MM-DD format.
 * @return string Html text content.
 */
function frontend_analytics_get_analytics( $page, $ga_start = '', $ga_end = '' ) {
    // NEW ANALYTICS
	$page = esc_url_raw($page);
    $start_date = '';
    $end_date = '';
    $dimensions = '';
    $sort = '';
    $filters = "ga:pagePath==".$page;
    $metrics = "ga:pageviews";
    $realtime = false;
    $limit = false;
    if (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'thisweek') {
        $start_date = date('Y-m-d', strtotime("-6 day"));
        $end_date = date('Y-m-d');
        $dimensions = "ga:date,ga:nthDay";
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'lastweek') {
        $start_date = date('Y-m-d', strtotime("-13 day"));
        $end_date = date('Y-m-d', strtotime("-7 day"));
        $dimensions = "ga:date,ga:nthDay";
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'thismonth') {
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d');
        $dimensions = "ga:date,ga:nthDay";
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'lastmonth') {
        $start_date = date('Y-m-01', strtotime("-1 month"));
        $end_date = date('Y-m-t', strtotime("-1 month"));
        $dimensions = "ga:date,ga:nthDay";
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'thisyear') {
        $start_date = date('Y')."-01-01";
        $end_date = date('Y-m-d');
        $dimensions = "ga:month,ga:nthMonth";
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'lastyear') {
        $start_date = date('Y', strtotime("-1 year"))."-01-01";
        $end_date = date('Y', strtotime("-1 year"))."-12-31";
        $dimensions = "ga:month,ga:nthMonth";
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'country') {
        $start_date = "14daysAgo";
        $end_date = "yesterday";
        $dimensions = "ga:country";
        $sort = "-ga:pageviews";
        $limit  = 5;
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'realtime') {
        $metrics = "rt:activeUsers";
        $realtime = true;
    }

    # Create a new Gdata call
    $gaApi = new Frontend_Analytics_API();

    # Check if Google successfully logged in
    if ( ! $gaApi->checkLogin() ) {
        echo json_encode( array( 'error' => __( 'Please check Google Analytics Settings', 'frontend-analytics' ) ) );
        return false;
    }

    $account = $gaApi->getSingleProfile();

    if ( ! isset( $account[0]['id'] ) ) {
        echo json_encode(array('error'=>__('Please check Google Analytics Settings','frontend-analytics')));
        return false;
    }

    $account = $account[0]['id'];

    # Set the account to the one requested
    $gaApi->setAccount( $account );

    # Get the metrics needed to build the visits graph;
    $stats = array();
    try {
        $stats = $gaApi->getMetrics( $metrics, $start_date, $end_date, $dimensions, $sort, $filters, $limit , $realtime );
    } catch ( Exception $e ) {
        print 'GA Summary Widget - there was a service error ' . $e->getCode() . ':' . $e->getMessage();
    }

    echo json_encode( $stats );
    exit;
}

function frontend_analytics_get_token() {
    $at = frontend_analytics_get_option( 'access_token' );
    $use_url = "https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=" . $at;
    $response = wp_remote_get( $use_url, array( 'timeout' => 15 ) );

    if ( ! empty( $response['response']['code'] ) && $response['response']['code'] == 200 ) { // access token is valid
		return $at;
    } else { // get new access token
        $refresh_at = frontend_analytics_get_option( 'refresh_token' );
        if ( ! $refresh_at ) {
            echo json_encode( array( 'error' => __( 'Not authorized, please click authorized in GD > Google analytic settings.', 'frontend-analytics' ) ) );
			exit;
        }

        $rat_url = "https://www.googleapis.com/oauth2/v3/token?";
        $client_id = "client_id=" . frontend_analytics_get_option('client_id');
        $client_secret = "&client_secret=" . frontend_analytics_get_option('client_secret');
        $refresh_token = "&refresh_token=" . $refresh_at;
        $grant_type = "&grant_type=refresh_token";

        $rat_url_use = $rat_url . $client_id . $client_secret . $refresh_token . $grant_type;

        $rat_response = wp_remote_post( $rat_url_use, array( 'timeout' => 15 ) );
        if ( ! empty( $rat_response['response']['code'] ) && $rat_response['response']['code'] == 200 ) {
            $parts = json_decode( $rat_response['body'] );
            frontend_analytics_update_option( 'access_token', $parts->access_token );
            return $parts->access_token;
        } else {
            echo json_encode( array( 'error' => __( 'Login failed', 'frontend-analytics' ) ) );
			exit;
        }
    }
}

/**
 * Outputs the google analytics section on page.
 *
 * Outputs the google analytics html if the current logged in user owns the post.
 *
 * @global WP_Post|null $post The current post, if available.
 * @since 1.0.0
 * @package Frontend_Analytics
 */
function frontend_analytics_display_analytics( $args = array() ) {
	global $post, $preview;

	if ( $preview || empty( $post ) ) {
		return;
	}

	$id = trim( frontend_analytics_get_option( 'account_id' ) );
	$month_last_day = max( (int) date( 't' ), (int) date( 't', strtotime( '-1 month' ) ) );
	$month_days = array();
	for ( $d = 1; $d <= $month_last_day; $d++ ) {
		$month_days[] = $d;
	}

	if ( ! $id ) {
		return; // if no Google Analytics ID then bail.
	}

	if ( ! frontend_analytics_check_post_google_analytics( $post ) ) {
		return;
	}

	$design_style = frontend_analytics_design_style();

	if ( empty( $args['height'] ) || absint( $args['height'] ) < 100 ) {
		$args['height'] = 200;
	}

	if ( $design_style ) {
		if ( empty( $args['btn_color'] ) ) {
			$args['btn_color'] = 'primary';
		}

		if ( $args['btn_size'] ) {
			switch ( $args['btn_size'] ) {
				case 'small':
					$args['btn_size'] = 'sm';
				break;
				case 'large':
					$args['btn_size'] = 'lg';
				break;
				case 'medium':
					$args['btn_size'] = '';
				break;
			}
		}
	}

    ob_start(); // Start buffering;
    /**
     * This is called before the edit post link html in the function frontend_analytics_display_analytics()
     *
     * @since 1.0.0
     */
    do_action( 'frontend_analytics_before_google_analytics' );
    
    $refresh_time = frontend_analytics_get_option( 'refresh_time', 5 );
    /**
     * Filter the time interval to check & refresh new users results.
     *
     * @since 1.0.0
     *
     * @param int $refresh_time Time interval to check & refresh new users results.
     */
    $refresh_time = apply_filters('frontend_analytics_refresh_time', $refresh_time);
    $refresh_time = absint( $refresh_time ) * 1000;
 
    $hide_refresh = 0;
    
    $auto_refresh = 1;
    $page_url = urlencode($_SERVER['REQUEST_URI']);
    ?>
<script type="text/javascript">
var gd_gaTimeOut;
var gd_gaTime = parseInt('<?php echo $refresh_time;?>');
var gd_gaHideRefresh = <?php echo (int)$hide_refresh;?>;
var gd_gaAutoRefresh = <?php echo $auto_refresh;?>;
var gd_gaPageToken = "<?php echo frontend_analytics_get_page_access_token($args['user_roles']);?>";
ga_data1 = false;
ga_data2 = false;
ga_data3 = false;
ga_data4 = false;
ga_data5 = false;
ga_data6 = false;
ga_au = 0;
setTimeout(function() {
jQuery(document).ready(function() {
	jQuery('.gdga-show-analytics').click(function(e) {
		jQuery(this).hide();
		jQuery(this).parent().find('.gdga-analytics-box').show();
		// load the JS file needed
		jQuery.getScript("<?php echo FRONTEND_ANALYTICS_PLUGIN_URL ."/assets/js/Chart.min.js";?>").done(function(script, textStatus) {
			// Set some global Chart.js defaults.
			Chart.defaults.global.animationSteps = 60;
			Chart.defaults.global.animationEasing = 'easeInOutQuart';
			Chart.defaults.global.responsive = true;
			Chart.defaults.global.maintainAspectRatio = false;

			gdga_weekVSweek();
			gdga_realtime(true);
		});
		
	});

	if (true) {
		jQuery('#gdga-loader-icon').click(function(e) {
			gdga_refresh();
			clearTimeout(gd_gaTimeOut);
			gdga_realtime();
		});
	}
}); 
}, 1000);

function gdga_weekVSweek() {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='. esc_html( $page_url ).'&ga_type=thisweek&pt='); ?>"+gd_gaPageToken, success: function(result){
		ga_data1 = jQuery.parseJSON(result);
		if(ga_data1.error){jQuery('#ga_stats').html(result);return;}
		gd_renderWeekOverWeekChart();
	}});

	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='.esc_html( $page_url ).'&ga_type=lastweek&pt='); ?>"+gd_gaPageToken, success: function(result){
		ga_data2 = jQuery.parseJSON(result);
		gd_renderWeekOverWeekChart();
	}});
}

function gdga_monthVSmonth() {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='.$page_url.'&ga_type=thismonth&pt='); ?>"+gd_gaPageToken, success: function(result){
		ga_data1 = jQuery.parseJSON(result);
		if(ga_data1.error){jQuery('#ga_stats').html(result);return;}
		gd_renderMonthOverMonthChart();
	}});

	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='.$page_url.'&ga_type=lastmonth&pt='); ?>"+gd_gaPageToken, success: function(result){
		ga_data2 = jQuery.parseJSON(result);
		gd_renderMonthOverMonthChart();
	}});
}

function gdga_yearVSyear() {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='.esc_html( $page_url ).'&ga_type=thisyear&pt='); ?>"+gd_gaPageToken, success: function(result){
		ga_data3 = jQuery.parseJSON(result);
		if(ga_data3.error){jQuery('#ga_stats').html(result);return;}

		gd_renderYearOverYearChart()
	}});

	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='.esc_html( $page_url ).'&ga_type=lastyear&pt='); ?>"+gd_gaPageToken, success: function(result){
		ga_data4 = jQuery.parseJSON(result);
		gd_renderYearOverYearChart()
	}});
}

function gdga_country() {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='.esc_html( $page_url ).'&ga_type=country&pt='); ?>"+gd_gaPageToken, success: function(result){
		ga_data5 = jQuery.parseJSON(result);
		if(ga_data5.error){jQuery('#ga_stats').html(result);return;}
		gd_renderTopCountriesChart();
	}});
}

function gdga_realtime(dom_ready) {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=frontend_analytics_stats&ga_page='.esc_html( $page_url ).'&ga_type=realtime&pt='); ?>"+gd_gaPageToken, success: function(result) {
		ga_data6 = jQuery.parseJSON(result);
		if (ga_data6.error) {
			jQuery('#ga_stats').html(result);
			return;
		}
		gd_renderRealTime(dom_ready);
	}});
}

function gd_renderRealTime(dom_ready) {
	if (typeof dom_ready === 'undefined') {
		gdga_refresh(true);
	}
	ga_au_old = ga_au;

	ga_au = ga_data6.totalsForAllResults["rt:activeUsers"];
	if (ga_au > ga_au_old) {
		jQuery('.gd-ActiveUsers').addClass("is-increasing");
	}

	if (ga_au < ga_au_old) {
		jQuery('.gd-ActiveUsers').addClass("is-decreasing");
	}

	jQuery('.gd-ActiveUsers-value').html(ga_au);

	if (gd_gaTime > 0 ) {
		// check for new users every 5 seconds
		gd_gaTimeOut = setTimeout(function() {
			jQuery('.gd-ActiveUsers').removeClass("is-increasing is-decreasing");
			gdga_realtime();
		}, gd_gaTime);
	}
}

/**
 * Draw the a chart.js doughnut chart with data from the specified view that
 * compares sessions from mobile, desktop, and tablet over the past two
 * weeks.
 */
function gd_renderTopCountriesChart() {
	if (ga_data5) {
		response = ga_data5;
		ga_data5 = false;
	} else {
		return;
	}

	jQuery('#gdga-chart-container').show();
	jQuery('#gdga-legend-container').show();
	gdga_refresh(true);
	jQuery('.gdga-type-container').show();
	jQuery('#gdga-select-analytic').prop('disabled', false);

	var data = [];
	data.labels = [];
	data.datasets = [];
	data.datasets[0] = {};
	data.datasets[0].label = "Countries";
	data.datasets[0].data = [];
	data.datasets[0].backgroundColor = [];

	var colors = ['#4D5360', '#949FB1', '#D4CCC5', '#E2EAE9', '#F7464A'];

	if (response.rows) {
		response.rows.forEach(function(row, i) {

			data.labels.push(row[0]);
			data.datasets[0].data.push(+row[1]);
			data.datasets[0].backgroundColor.push(colors[i]);

		});

		new Chart(makeCanvas('gdga-chart-container'), {
			// The type of chart we want to create
			type: 'doughnut',
			// The data for our dataset
			data: data,
			// Configuration options go here
			options: {}
		});
	} else {
		gdga_noResults();
	}
}

function gdga_noResults() {
	jQuery('#gdga-chart-container').html('<?php _e('No results available','frontend-analytics');?>');
	jQuery('#gdga-legend-container').html('');
}

/**
 * Draw the a chart.js bar chart with data from the specified view that
 * overlays session data for the current year over session data for the
 * previous year, grouped by month.
 */
function gd_renderYearOverYearChart() {
	if (ga_data3 && ga_data4) {
		thisYear = ga_data3;
		lastYear = ga_data4;
		ga_data3 = false;
		ga_data4 = false;
	} else {
		return;
	}

	jQuery('#gdga-chart-container').show();
	jQuery('#gdga-legend-container').show();
	gdga_refresh(true);
	jQuery('.gdga-type-container').show();
	jQuery('#gdga-select-analytic').prop('disabled', false);

	Promise.all([thisYear, lastYear]).then(function(results) {
		var data1 = results[0].rows.map(function(row) { return +row[2]; });
		var data2 = results[1].rows.map(function(row) { return +row[2]; });
		//var labelsN = results[0].rows.map(function(row) { return +row[1]; });

		var labels = ['<?php _e('Jan', 'frontend-analytics');?>',
			'<?php _e('Feb', 'frontend-analytics');?>',
			'<?php _e('Mar', 'frontend-analytics');?>',
			'<?php _e('Apr', 'frontend-analytics');?>',
			'<?php _e('May', 'frontend-analytics');?>',
			'<?php _e('Jun', 'frontend-analytics');?>',
			'<?php _e('Jul', 'frontend-analytics');?>',
			'<?php _e('Aug', 'frontend-analytics');?>',
			'<?php _e('Sep', 'frontend-analytics');?>',
			'<?php _e('Oct', 'frontend-analytics');?>',
			'<?php _e('Nov', 'frontend-analytics');?>',
			'<?php _e('Dec', 'frontend-analytics');?>'];

		// Ensure the data arrays are at least as long as the labels array.
		// Chart.js bar charts don't (yet) accept sparse datasets.
		for (var i = 0, len = labels.length; i < len; i++) {
			if (data1[i] === undefined) data1[i] = null;
			if (data2[i] === undefined) data2[i] = null;
		}

		var data = {
			labels : labels,
			datasets : [
				{
					label: '<?php _e('Last Year', 'frontend-analytics');?>',
					backgroundColor : "rgba(220,220,220,0.5)",
					borderColor : "rgba(220,220,220,1)",
					data : data2
				},
				{
					label: '<?php _e('This Year', 'frontend-analytics');?>',
					backgroundColor : "rgba(151,187,205,0.5)",
					borderColor : "rgba(151,187,205,1)",
					data : data1
				}
			]
		};

		new Chart(makeCanvas('gdga-chart-container'), {
			// The type of chart we want to create
			type: 'bar',
			// The data for our dataset
			data: data,
			// Configuration options go here
			options: {}
		});
	}).catch(function(err) {
		console.error(err.stack);
	})
}

/**
 * Draw the a chart.js line chart with data from the specified view that
 * overlays session data for the current week over session data for the
 * previous week.
 */
function gd_renderWeekOverWeekChart() {
	if(ga_data1 && ga_data2){
		thisWeek = ga_data1;
		lastWeek = ga_data2;
		ga_data1 = false;
		ga_data2 = false;
	}else{
		return;
	}

	jQuery('#gdga-chart-container').show();
	jQuery('#gdga-legend-container').show();
	gdga_refresh(true);
	jQuery('.gdga-type-container').show();
	jQuery('#gdga-select-analytic').prop('disabled', false);

	Promise.all([thisWeek, lastWeek]).then(function(results) {
		var data1 = results[0].rows.map(function(row) { return +row[2]; });
		var data2 = results[1].rows.map(function(row) { return +row[2]; });
		var labels = results[1].rows.map(function(row) { return +row[0]; });

		<?php
		// Here we list the shorthand days of the week so it can be used in translation.
		__("Mon",'frontend-analytics');
		__("Tue",'frontend-analytics');
		__("Wed",'frontend-analytics');
		__("Thu",'frontend-analytics');
		__("Fri",'frontend-analytics');
		__("Sat",'frontend-analytics');
		__("Sun",'frontend-analytics');
		?>

		labels = [
			"<?php _e(date('D', strtotime("+1 day")),'frontend-analytics'); ?>",
			"<?php _e(date('D', strtotime("+2 day")),'frontend-analytics'); ?>",
			"<?php _e(date('D', strtotime("+3 day")),'frontend-analytics'); ?>",
			"<?php _e(date('D', strtotime("+4 day")),'frontend-analytics'); ?>",
			"<?php _e(date('D', strtotime("+5 day")),'frontend-analytics'); ?>",
			"<?php _e(date('D', strtotime("+6 day")),'frontend-analytics'); ?>",
			"<?php _e(date('D', strtotime("+7 day")),'frontend-analytics'); ?>"
		];

		var data = {
			labels : labels,
			datasets : [
				{
					label: '<?php _e('This Week', 'frontend-analytics');?>',
					backgroundColor : "rgba(151,187,205,0.5)",
					borderColor : "rgba(151,187,205,1)",
					pointBackgroundColor : "rgba(151,187,205,1)",
					pointBorderColor : "#fff",
					data : data1
				},
				{
					label: '<?php _e('Last Week', 'frontend-analytics');?>',
					backgroundColor : "rgba(220,220,220,0.5)",
					borderColor : "rgba(220,220,220,1)",
					pointBackgroundColor : "rgba(220,220,220,1)",
					pointBorderColor : "#fff",
					data : data2
				}

			]
		};

		new Chart(makeCanvas('gdga-chart-container'), {
			// The type of chart we want to create
			type: 'line',
			// The data for our dataset
			data: data,
			// Configuration options go here
			options: {}
		});

	});
}

function gd_renderMonthOverMonthChart() {
	if(ga_data1 && ga_data2){
		thisMonth = ga_data1;
		lastMonth = ga_data2;
		ga_data1 = false;
		ga_data2 = false;
	}else{
		return;
	}

	jQuery('#gdga-chart-container').show();
	jQuery('#gdga-legend-container').show();
	gdga_refresh(true);
	jQuery('.gdga-type-container').show();
	jQuery('#gdga-select-analytic').prop('disabled', false);

	Promise.all([thisMonth, lastMonth]).then(function(results) {
		var data1 = results[0].rows.map(function(row) { return +row[2]; });
		var data2 = results[1].rows.map(function(row) { return +row[2]; });
		var labels = results[1].rows.map(function(row) { return +row[0]; });

		labels = [<?php echo implode( ",", $month_days ) ?>];
		
		for (var i = 0, len = labels.length; i < len; i++) {
			if (data1[i] === undefined) data1[i] = null;
			if (data2[i] === undefined) data2[i] = 0;
		}

		var data = {
			labels : labels,
			datasets : [
				{
					label: '<?php _e('Last Month', 'frontend-analytics');?>',
					fillColor : "rgba(220,220,220,0.5)",
					strokeColor : "rgba(220,220,220,1)",
					pointColor : "rgba(220,220,220,1)",
					pointStrokeColor : "#fff",
					data : data2
				},
				{
					label: '<?php _e('This Month', 'frontend-analytics');?>',
					fillColor : "rgba(151,187,205,0.5)",
					strokeColor : "rgba(151,187,205,1)",
					pointColor : "rgba(151,187,205,1)",
					pointStrokeColor : "#fff",
					data : data1
				}
			]
		};

		new Chart(makeCanvas('gdga-chart-container'), {
			// The type of chart we want to create
			type: 'line',
			// The data for our dataset
			data: data,
			// Configuration options go here
			options: {}
		});
	});
}

/**
 * Create a new canvas inside the specified element. Set it to be the width
 * and height of its container.
 * @param {string} id The id attribute of the element to host the canvas.
 * @return {RenderingContext} The 2D canvas context.
 */
function makeCanvas(id) {
	var container = document.getElementById(id);
	var canvas = document.createElement('canvas');
	var ctx = canvas.getContext('2d');

	container.innerHTML = '';
	canvas.width = container.offsetWidth;
	canvas.height = container.offsetHeight;
	container.appendChild(canvas);

	return ctx;
}

/**
 * Create a visual legend inside the specified element based off of a
 * Chart.js dataset.
 * @param {string} id The id attribute of the element to host the legend.
 * @param {Array.<Object>} items A list of labels and colors for the legend.
 */
function generateLegend(id, items) {
	var legend = document.getElementById(id);
	legend.innerHTML = items.map(function(item) {
		var color = item.color || item.fillColor;
		var label = item.label;
		<?php if ( $design_style ) { ?>
		return '<div class="btn btn-sm m-auto shadow-none py-0 px-3"><i style="background:' + color + '" class="mr-1 badge badge-pill p-2 d-inline-block align-middle"></i><span class="d-inline-block align-middle">' + label + '</span></div>';
		<?php } else { ?>
		return '<li><i style="background:' + color + '"></i>' + label + '</li>';
		<?php } ?>
	}).join('');
}

function gdga_select_option() {
	jQuery('#gdga-select-analytic').prop('disabled', true);
	gdga_refresh();

	gaType = jQuery('#gdga-select-analytic').val();

	if (gaType == 'weeks') {
		gdga_weekVSweek();
	} else if (gaType == 'months') {
		gdga_monthVSmonth();
	} else if (gaType == 'years') {
		gdga_yearVSyear();
	} else if (gaType == 'country') {
		gdga_country();
	}
}

function gdga_refresh(stop) {
	if (typeof stop !== 'undefined' && stop) {
		if (gd_gaAutoRefresh === 1 || gd_gaHideRefresh == 1) {
			jQuery('#gdga-loader-icon').hide();
		} else {
			jQuery('#gdga-loader-icon .fa-refresh').removeClass('fa-spin');
		}
	} else {
		if (gd_gaAutoRefresh === 1 || gd_gaHideRefresh == 1) {
			jQuery('#gdga-loader-icon').show();
		} else {
			if (!jQuery('#gdga-loader-icon .fa-refresh').hasClass('fa-spin')) {
				jQuery('#gdga-loader-icon .fa-refresh').addClass('fa-spin');
			}
		}
	}
}
</script>
<?php if ( $design_style ) {
	$btn_class = '';
	if ( ! empty( $args['btn_color'] ) ) {
		$btn_class .= ' btn-' . sanitize_html_class( $args['btn_color'] );
	}
	if ( ! empty( $args['btn_size'] ) ) {
		$btn_class .= ' btn-' . sanitize_html_class( $args['btn_size'] );
	}
	$btn_wrap_class = ' text-left';
	if ( ! empty( $args['btn_alignment'] ) ) {
		if ( $args['btn_alignment'] == 'block' ) {
			$btn_class .= ' w-100';
			$btn_wrap_class = '';
		} else {
			$btn_wrap_class .= ' text-' . sanitize_html_class( $args['btn_alignment'] );
		}
	}
	?>
		<div class="gdga-show-analytics<?php echo $btn_wrap_class; ?>"><button role="button" class="btn<?php echo $btn_class; ?>"><i class="fas fa-chart-bar mr-1" aria-hidden="true"></i><?php echo ! empty( $args['button_text'] ) ? esc_attr( $args['button_text'] ) : __('Show Google Analytics', 'frontend-analytics');?></button></div>
		<div id="ga_stats" class="gdga-analytics-box card" style="display:none">
			<div class="card-header p-3">
				<div class="gd-ActiveUsers btn btn-sm btn-info float-right py-1 px-2 align-middle"><span id="gdga-loader-icon" class="mr-1" title="<?php esc_attr_e("Refresh", 'frontend-analytics');?>"><i class="fa fa-refresh fa-spin" aria-hidden="true"></i></span><?php _e("Active Users:", 'frontend-analytics');?> <span class="gd-ActiveUsers-value badge badge-light badge-pill">0</span></div>
				<div id="ga-analytics-title" class="h5 m-0 card-title align-middle"><i class="fas fa-chart-bar mr-1" aria-hidden="true"></i><?php _e("Analytics", 'frontend-analytics');?></div>
			</div>
			<div class="card-body">
				<div class="gdga-type-container form-group" style="display:none">
					<?php
					echo aui()->select( array(
						'id' => 'gdga-select-analytic',
						'name' => '',
						'title' => '',
						'placeholder' => '',
						'value' => '',
						'label_show' => false,
						'label' => '',
						'options' => array(
							'weeks' => __( "Last Week vs This Week", 'frontend-analytics' ),
							'months' => __( "This Month vs Last Month", 'frontend-analytics' ),
							'years' => __( "This Year vs Last Year", 'frontend-analytics' ),
							'country' => __( "Top Countries", 'frontend-analytics' ),
						),
						'select2' => true,
						'extra_attributes' => array(
							'onchange' => 'gdga_select_option();'
						),
					) );
					?>
				</div>
				<div class="Chartjs-figure w-100" id="gdga-chart-container" style="display:none;height:<?php echo absint( $args['height'] ); ?>px"></div>
				<div class="Chartjs-legend text-center" id="gdga-legend-container"></div>
			</div>
		</div>
<?php } else { ?>
<style>
#gdga-chart-container{clear:both}
.gdga-type-container{width:100%;display:block;clear:both}
.gdga-type-container > .select2-container{width:100% !important}
.geodir-details-sidebar-google-analytics{min-height:60px}
#ga_stats #gd-active-users-container{float:right;margin:0 0 10px}
#gdga-select-analytic{clear:both;width:100%}
#ga_stats #ga-analytics-title{float:left;font-weight:bold}
#ga_stats #gd-active-users-container{float:right}
.Chartjs{font-size:.85em}
.Chartjs-figure{height:<?php echo absint( $args['height'] ); ?>px;width:100%;display:none}
.Chartjs-legend{list-style:none;margin:0;padding:1em 0 0;text-align:center;width:100%;display:none}
.Chartjs-legend>li{display:inline-block;padding:.25em .5em}
.Chartjs-legend>li>i{display:inline-block;height:1em;margin-right:.5em;vertical-align:-.1em;width:1em}
@media (min-width:570px){.Chartjs-figure{margin-right:1.5em}}
.gd-ActiveUsers{background:#f3f2f0;border:1px solid #d4d2d0;border-radius:4px;font-weight:300;padding:.5em 1.5em;white-space:nowrap}
.gd-ActiveUsers-value{display:inline-block;font-weight:600;margin-right:-.25em}
.gd-ActiveUsers.is-increasing{-webkit-animation:increase 3s;animation:increase 3s}
.gd-ActiveUsers.is-decreasing{-webkit-animation:decrease 3s;animation:decrease 3s}
@-webkit-keyframes increase{10%{background-color:#eaffea;border-color:hsla(120,100%,25%,.5);color:hsla(120,100%,25%,1)}}
@keyframes increase{10%{background-color:#eaffea;border-color:hsla(120,100%,25%,.5);color:hsla(120,100%,25%,1)}}
@-webkit-keyframes decrease{10%{background-color:#ffeaea;border-color:hsla(0,100%,50%,.5);color:red}}
@keyframes decrease{10%{background-color:#ffeaea;border-color:hsla(0,100%,50%,.5);color:red}}
#gdga-loader-icon svg,#gdga-loader-icon i{margin:0 10px 0 -10px;color:#333333;cursor:pointer}
.#gdga-loader-icon .fa-spin{-webkit-animation-duration:1.5s;animation-duration:1.5s}
 </style>
        <button type="button" class="gdga-show-analytics"><?php echo !empty($args['button_text']) ? esc_attr($args['button_text']) : __('Show Google Analytics', 'frontend-analytics');?></button>
        <span id="ga_stats" class="gdga-analytics-box" style="display:none">
            <div id="ga-analytics-title"><?php _e("Analytics", 'frontend-analytics');?></div>
            <div id="gd-active-users-container">
                <div class="gd-ActiveUsers"><span id="gdga-loader-icon" title="<?php esc_attr_e("Refresh", 'frontend-analytics');?>"><i class="fa fa-refresh fa-spin" aria-hidden="true"></i></span><?php _e("Active Users:", 'frontend-analytics');?> <b class="gd-ActiveUsers-value">0</b>
                </div>
            </div>
            <div class="gdga-type-container" style="display:none">
				<select id="gdga-select-analytic" class="geodir-select" onchange="gdga_select_option();">
					<option value="weeks"><?php _e("Last Week vs This Week", 'frontend-analytics');?></option>
					<option value="months"><?php _e("This Month vs Last Month", 'frontend-analytics');?></option>
					<option value="years"><?php _e("This Year vs Last Year", 'frontend-analytics');?></option>
					<option value="country"><?php _e("Top Countries", 'frontend-analytics');?></option>
				</select>
			</div>
            <div class="Chartjs-figure" id="gdga-chart-container"></div>
            <ol class="Chartjs-legend" id="gdga-legend-container"></ol>
        </span>
    <?php
    }
    /**
     * This is called after the edit post link html in the function geodir_detail_page_google_analytics()
     *
     * @since 1.0.0
     */
    do_action('geodir_after_google_analytics');
    $content_html = ob_get_clean();
    if (trim($content_html) != '')
        $content_html = '<div class="geodir-details-sidebar-google-analytics">' . $content_html . '</div>';
    if ((int)frontend_analytics_get_option('disable_google_analytics_section') != 1) {
        /**
         * Filter the geodir_edit_post_link() function content.
         *
         * @param string $content_html The output html of the geodir_edit_post_link() function.
         */
        echo $content_html = apply_filters('geodir_google_analytic_html', $content_html);
    }
}

/**
 * Loads Google Analytics JS on header.
 *
 *
 * @since 1.0.0
 */
function frontend_analytics_add_tracking_code() {
    if ( frontend_analytics_get_option( 'add_tracking_code' ) && ( $account_id = frontend_analytics_get_option( 'account_id' ) ) ) { ?>
<script>
	(function(i,s,o,g,r,a,m){ i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
	ga('create', '<?php echo esc_attr( $account_id ); ?>', 'auto');
	<?php if ( frontend_analytics_get_option( 'anonymize_ip' ) ) { echo "ga('set', 'anonymizeIP', true);"; } ?>
	ga('send', 'pageview');
</script>
        <?php
    } elseif ( ( $tracking_code = frontend_analytics_get_option( 'tracking_code' ) ) && ! frontend_analytics_get_option( 'account_id' ) ) {
        echo stripslashes( frontend_analytics_get_option( 'tracking_code' ) );
    }
}

function frontend_analytics_check_post_google_analytics( $post ) {
	return apply_filters( 'frontend_analytics_check_post_google_analytics', true, $post );
}


function frontend_analytics_get_option( $name ){
	$fa 			= frontend_analytics();
	$options 		= $fa->get_options();

	if( isset( $options[ $name ] ) ) {
		return $options[ $name ];
	}

	return null;
}

function frontend_analytics_update_option( $name, $value ){
	$fa 			= frontend_analytics();
	$options 		= $fa->get_options();
	$options[$name] = $value;
	$fa->update_options( $options );
}

/**
 * Generate a specific access token for a page and access level.
 * 
 * @param string $access_level
 *
 * @return string
 */
function frontend_analytics_get_page_access_token($access_level = 'administrator',$path = ''){
	$token = '';
	$path = $path ? wp_unslash( $path ) : wp_unslash( $_SERVER['REQUEST_URI'] );
	if($path && $access_level){
		$token = wp_hash($path.$access_level);
	}

	return $token;
}

/**
 * Check if a page access token is valid for the specific user type.
 *
 * @param $token
 * @param $path
 *
 * @return bool
 */
function frontend_analytics_validate_page_access_token($token,$path){
	$result = false;
	$user_id = get_current_user_id();

	if($token){
		if($token == frontend_analytics_get_page_access_token('all',$path )){
			$result = true;
		}elseif($user_id && $token == frontend_analytics_get_page_access_token('all-logged-in',$path )){
			$result = true;
		}elseif($user_id && $token == frontend_analytics_get_page_access_token('author',$path )){
			$result = true;
		}elseif($user_id && current_user_can( 'manage_options' ) && $token == frontend_analytics_get_page_access_token('administrator',$path )){
			$result = true;
		}
	}

	return $result;
}

/**
 * Get the design style for the site if available.
 *
 * @since 2.0.0
 *
 * @return mixed
 */
function frontend_analytics_design_style() {
	if ( function_exists( 'geodir_design_style' ) ) {
		$style = geodir_design_style();
	} else {
		$style = '';
	}

	return $style;
}