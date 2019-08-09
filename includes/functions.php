<?php
/**
 * Google analystics related functions.
 *
 * @since 1.0.0
 * @package GeoDirectory
 */

function goedir_ga_register_widgets() {
	if ( get_option( 'geodir_ga_version' ) ) {
		register_widget( 'GeoDir_Google_Analytics_Widget_Post_Analytics' );
	}
}

/**
 * Formats seconds into to h:m:s.
 *
 * @since 1.0.0
 *
 * @param int  $sec The number of seconds.
 * @param bool $padHours Whether add leading zero for less than 10 hours. Default false.
 * @return string h:m:s format.
 */
function geodir_ga_sec2hms( $sec, $padHours = false ) {
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
function geodir_ga_get_analytics( $page, $ga_start, $ga_end ) {
    // NEW ANALYTICS
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
        $sort = "ga:pageviews";
        $limit  = 5;
    } elseif (isset($_REQUEST['ga_type']) && $_REQUEST['ga_type'] == 'realtime') {
        $metrics = "rt:activeUsers";
        $realtime = true;
    }

    # Create a new Gdata call
    $gaApi = new GeoDir_Google_Analytics_API();

    # Check if Google sucessfully logged in
    if ( ! $gaApi->checkLogin() ) {
        echo json_encode( array( 'error' => __( 'Please check Google Analytics Settings', 'geodir-ga' ) ) );
        return false;
    }

    $account = $gaApi->getSingleProfile();

    if ( ! isset( $account[0]['id'] ) ) {
        echo json_encode(array('error'=>__('Please check Google Analytics Settings','geodir-ga')));
        return false;
    }

    $account = $account[0]['id'];

    # Set the account to the one requested
    $gaApi->setAccount( $account );

    # Get the metrics needed to build the visits graph;
    try {
        $stats = $gaApi->getMetrics( $metrics, $start_date, $end_date, $dimensions, $sort, $filters, $limit , $realtime );
    } catch ( Exception $e ) {
        print 'GA Summary Widget - there was a service error ' . $e->getCode() . ':' . $e->getMessage();
    }

    echo json_encode( $stats );
    exit;
}

function geodir_ga_get_token() {
    $at = geodir_get_option( 'gd_ga_access_token' );
    $use_url = "https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=" . $at;
    $response = wp_remote_get( $use_url, array( 'timeout' => 15 ) );

    if ( ! empty( $response['response']['code'] ) && $response['response']['code'] == 200 ) { // access token is valid
		return $at;
    } else { // get new access token
        $refresh_at = geodir_get_option( 'gd_ga_refresh_token' );
        if ( ! $refresh_at ) {
            echo json_encode( array( 'error' => __( 'Not authorized, please click authorized in GD > Google analytic settings.', 'geodir-ga' ) ) );
			exit;
        }

        $rat_url = "https://www.googleapis.com/oauth2/v3/token?";
        $client_id = "client_id=" . geodir_get_option('ga_client_id');
        $client_secret = "&client_secret=" . geodir_get_option('ga_client_secret');
        $refresh_token = "&refresh_token=" . $refresh_at;
        $grant_type = "&grant_type=refresh_token";

        $rat_url_use = $rat_url . $client_id . $client_secret . $refresh_token . $grant_type;

        $rat_response = wp_remote_post( $rat_url_use, array( 'timeout' => 15 ) );
        if ( ! empty( $rat_response['response']['code'] ) && $rat_response['response']['code'] == 200 ) {
            $parts = json_decode( $rat_response['body'] );
            geodir_update_option( 'gd_ga_access_token', $parts->access_token );
            return $parts->access_token;
        } else {
            echo json_encode( array( 'error' => __( 'Login failed', 'geodir-ga' ) ) );
			exit;
        }
    }
}

/**
 * Outputs the google analytics section on details page.
 *
 * Outputs the google analytics html if the current logged in user owns the post.
 *
 * @global WP_Post|null $post The current post, if available.
 * @since 1.0.0
 * @package GeoDirectory
 */
function geodir_ga_display_analytics($args = array()) {
    global $post, $preview;

    if ( $preview || empty( $post ) ) {
		return;
	}

    $id = trim( geodir_get_option( 'ga_account_id' ) );

    if ( ! $id ) {
        return; // if no Google Analytics ID then bail.
    }

	if ( ! geodir_ga_check_post_google_analytics( $post ) ) {
		return;
	}

    ob_start(); // Start buffering;
    /**
     * This is called before the edit post link html in the function geodir_detail_page_google_analytics()
     *
     * @since 1.0.0
     */
    do_action( 'geodir_before_google_analytics' );
    
    $refresh_time = geodir_get_option( 'ga_refresh_time', 5 );
    /**
     * Filter the time interval to check & refresh new users results.
     *
     * @since 1.0.0
     *
     * @param int $refresh_time Time interval to check & refresh new users results.
     */
    $refresh_time = apply_filters('geodir_google_analytics_refresh_time', $refresh_time);
    $refresh_time = absint( $refresh_time ) * 1000;
    
    $hide_refresh = geodir_get_option('ga_auto_refresh');
    
    $auto_refresh = $hide_refresh && $refresh_time && $refresh_time > 0 ? 1 : 0;
    if (geodir_get_option('ga_stats')) {
        $page_url = urlencode($_SERVER['REQUEST_URI']);
        ?>
<script type="text/javascript">
var gd_gaTimeOut;
var gd_gaTime = parseInt('<?php echo $refresh_time;?>');
var gd_gaHideRefresh = <?php echo (int)$hide_refresh;?>;
var gd_gaAutoRefresh = <?php echo $auto_refresh;?>;
ga_data1 = false;
ga_data2 = false;
ga_data3 = false;
ga_data4 = false;
ga_data5 = false;
ga_data6 = false;
ga_au = 0;
jQuery(document).ready(function() {
	// Set some global Chart.js defaults.
	Chart.defaults.global.animationSteps = 60;
	Chart.defaults.global.animationEasing = 'easeInOutQuart';
	Chart.defaults.global.responsive = true;
	Chart.defaults.global.maintainAspectRatio = false;

	jQuery('.gdga-show-analytics').click(function(e) {
		jQuery(this).hide();
		jQuery('.gdga-analytics-box').show();
		gdga_weekVSweek();
		gdga_realtime(true);
	});

	if (gd_gaAutoRefresh !== 1) {
		jQuery('#gdga-loader-icon').click(function(e) {
			gdga_refresh();
			clearTimeout(gd_gaTimeOut);
			gdga_realtime();
		});
	}
});

function gdga_weekVSweek() {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=geodir_ga_stats&ga_page='.$page_url.'&ga_type=thisweek'); ?>", success: function(result){
		ga_data1 = jQuery.parseJSON(result);
		if(ga_data1.error){jQuery('#ga_stats').html(result);return;}
		gd_renderWeekOverWeekChart();
	}});

	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=geodir_ga_stats&ga_page='.$page_url.'&ga_type=lastweek'); ?>", success: function(result){
		ga_data2 = jQuery.parseJSON(result);
		gd_renderWeekOverWeekChart();
	}});
}

function gdga_yearVSyear() {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=geodir_ga_stats&ga_page='.$page_url.'&ga_type=thisyear'); ?>", success: function(result){
		ga_data3 = jQuery.parseJSON(result);
		if(ga_data3.error){jQuery('#ga_stats').html(result);return;}

		gd_renderYearOverYearChart()
	}});

	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=geodir_ga_stats&ga_page='.$page_url.'&ga_type=lastyear'); ?>", success: function(result){
		ga_data4 = jQuery.parseJSON(result);
		gd_renderYearOverYearChart()
	}});
}

function gdga_country() {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=geodir_ga_stats&ga_page='.$page_url.'&ga_type=country'); ?>", success: function(result){
		ga_data5 = jQuery.parseJSON(result);
		if(ga_data5.error){jQuery('#ga_stats').html(result);return;}
		gd_renderTopCountriesChart();
	}});
}

function gdga_realtime(dom_ready) {
	jQuery.ajax({url: "<?php echo admin_url('admin-ajax.php?action=geodir_ga_stats&ga_page='.$page_url.'&ga_type=realtime'); ?>", success: function(result) {
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

	if (gd_gaTime > 0 && gd_gaAutoRefresh === 1) {
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
	var colors = ['#4D5360', '#949FB1', '#D4CCC5', '#E2EAE9', '#F7464A'];

	if (response.rows) {
		response.rows.forEach(function(row, i) {
			data.push({
				label: row[0],
				value: +row[1],
				color: colors[i]
			});
		});

		new Chart(makeCanvas('gdga-chart-container')).Doughnut(data);
		generateLegend('gdga-legend-container', data);
	} else {
		gdga_noResults();
	}
}

function gdga_noResults() {
	jQuery('#gdga-chart-container').html('<?php _e('No results available','geodir-ga');?>');
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

	// Adjust `now` to experiment with different days, for testing only...
	var now = moment(); // .subtract(3, 'day');

	Promise.all([thisYear, lastYear]).then(function(results) {
		var data1 = results[0].rows.map(function(row) { return +row[2]; });
		var data2 = results[1].rows.map(function(row) { return +row[2]; });
		//var labelsN = results[0].rows.map(function(row) { return +row[1]; });

		var labels = ['<?php _e('Jan', 'geodir-ga');?>',
			'<?php _e('Feb', 'geodir-ga');?>',
			'<?php _e('Mar', 'geodir-ga');?>',
			'<?php _e('Apr', 'geodir-ga');?>',
			'<?php _e('May', 'geodir-ga');?>',
			'<?php _e('Jun', 'geodir-ga');?>',
			'<?php _e('Jul', 'geodir-ga');?>',
			'<?php _e('Aug', 'geodir-ga');?>',
			'<?php _e('Sep', 'geodir-ga');?>',
			'<?php _e('Oct', 'geodir-ga');?>',
			'<?php _e('Nov', 'geodir-ga');?>',
			'<?php _e('Dec', 'geodir-ga');?>'];

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
					label: '<?php _e('Last Year', 'geodir-ga');?>',
					fillColor : "rgba(220,220,220,0.5)",
					strokeColor : "rgba(220,220,220,1)",
					data : data2
				},
				{
					label: '<?php _e('This Year', 'geodir-ga');?>',
					fillColor : "rgba(151,187,205,0.5)",
					strokeColor : "rgba(151,187,205,1)",
					data : data1
				}
			]
		};

		new Chart(makeCanvas('gdga-chart-container')).Bar(data);
		generateLegend('gdga-legend-container', data.datasets);
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

	// Adjust `now` to experiment with different days, for testing only...
	var now = moment();

	Promise.all([thisWeek, lastWeek]).then(function(results) {
		var data1 = results[0].rows.map(function(row) { return +row[2]; });
		var data2 = results[1].rows.map(function(row) { return +row[2]; });
		var labels = results[1].rows.map(function(row) { return +row[0]; });

		<?php
		// Here we list the shorthand days of the week so it can be used in translation.
		__("Mon",'geodir-ga');
		__("Tue",'geodir-ga');
		__("Wed",'geodir-ga');
		__("Thu",'geodir-ga');
		__("Fri",'geodir-ga');
		__("Sat",'geodir-ga');
		__("Sun",'geodir-ga');
		?>

		labels = [
			"<?php _e(date('D', strtotime("+1 day")),'geodir-ga'); ?>",
			"<?php _e(date('D', strtotime("+2 day")),'geodir-ga'); ?>",
			"<?php _e(date('D', strtotime("+3 day")),'geodir-ga'); ?>",
			"<?php _e(date('D', strtotime("+4 day")),'geodir-ga'); ?>",
			"<?php _e(date('D', strtotime("+5 day")),'geodir-ga'); ?>",
			"<?php _e(date('D', strtotime("+6 day")),'geodir-ga'); ?>",
			"<?php _e(date('D', strtotime("+7 day")),'geodir-ga'); ?>"
		];

		var data = {
			labels : labels,
			datasets : [
				{
					label: '<?php _e('Last Week', 'geodir-ga');?>',
					fillColor : "rgba(220,220,220,0.5)",
					strokeColor : "rgba(220,220,220,1)",
					pointColor : "rgba(220,220,220,1)",
					pointStrokeColor : "#fff",
					data : data2
				},
				{
					label: '<?php _e('This Week', 'geodir-ga');?>',
					fillColor : "rgba(151,187,205,0.5)",
					strokeColor : "rgba(151,187,205,1)",
					pointColor : "rgba(151,187,205,1)",
					pointStrokeColor : "#fff",
					data : data1
				}
			]
		};

		new Chart(makeCanvas('gdga-chart-container')).Line(data);
		generateLegend('gdga-legend-container', data.datasets);
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
		return '<li><i style="background:' + color + '"></i>' + label + '</li>';
	}).join('');
}

function gdga_select_option() {
	jQuery('#gdga-select-analytic').prop('disabled', true);
	gdga_refresh();

	gaType = jQuery('#gdga-select-analytic').val();

	if (gaType == 'weeks') {
		gdga_weekVSweek();
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
.Chartjs-figure{height:200px;width:100%;display:none}
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
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/1.0.2/Chart.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.10.2/moment.min.js"></script>
        <button type="button" class="gdga-show-analytics"><?php echo !empty($args['button_text']) ? esc_attr($args['button_text']) : __('Show Google Analytics', 'geodir-ga');?></button>
        <span id="ga_stats" class="gdga-analytics-box" style="display:none">
            <div id="ga-analytics-title"><?php _e("Analytics", 'geodir-ga');?></div>
            <div id="gd-active-users-container">
                <div class="gd-ActiveUsers"><span id="gdga-loader-icon" title="<?php esc_attr_e("Refresh", 'geodir-ga');?>"><i class="fa fa-refresh fa-spin" aria-hidden="true"></i></span><?php _e("Active Users:", 'geodir-ga');?> <b class="gd-ActiveUsers-value">0</b>
                </div>
            </div>
            <div class="gdga-type-container" style="display:none">
				<select id="gdga-select-analytic" class="geodir-select" onchange="gdga_select_option();">
					<option value="weeks"><?php _e("Last Week vs This Week", 'geodir-ga');?></option>
					<option value="years"><?php _e("This Year vs Last Year", 'geodir-ga');?></option>
					<option value="country"><?php _e("Top Countries", 'geodir-ga');?></option>
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
    if ((int)geodir_get_option('geodir_disable_google_analytics_section') != 1) {
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
 * WP Admin -> Geodirectory -> Settings -> Google Analytics -> Google analytics tracking code.
 *
 * @since 1.0.0
 * @package GeoDirectory
 */
function geodir_ga_add_tracking_code() {
    if ( geodir_get_option( 'ga_add_tracking_code' ) && ( $account_id = geodir_get_option( 'ga_account_id' ) ) ) { ?>
<script>
	(function(i,s,o,g,r,a,m){ i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
	ga('create', '<?php echo esc_attr( $account_id ); ?>', 'auto');
	<?php if ( geodir_get_option( 'ga_anonymize_ip' ) ) { echo "ga('set', 'anonymizeIP', true);"; } ?>
	ga('send', 'pageview');
</script>
        <?php
    } elseif ( ( $tracking_code = geodir_get_option( 'ga_tracking_code' ) ) && ! geodir_get_option( 'ga_account_id' ) ) {
        echo stripslashes( geodir_get_option( 'ga_tracking_code' ) );
    }
}

function geodir_ga_check_post_google_analytics( $post ) {
	$package = geodir_get_post_package( $post );

	$check = ! empty( $package->google_analytics ) ? true : false;

	return apply_filters( 'geodir_ga_check_post_google_analytics', $check, $post );
}