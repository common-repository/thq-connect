<?php
/**
* THQ-CONNECT CALENDAR.PHP
* Enables the TidyHQ Calendar
*/
	
/**
* CALENDAR WIDGET
*/
class TidyConnectCalendarWidget extends WP_Widget {
	private $currentYear;
	private $currentMonth;
	private $currentDay;
	private $lastYear;
	private $lastMonth;
	private $nextYear;
	private $nextMonth;
	private $firstDayOfWeek;
	private $firstDayOfMonth;
	private $daysInMonth;
	private $days_in_lastmonth;
	private $newFirstDayOfMonth;
	private $newFirstDayOfWeek;
	private $offset;
	private $newDays;
	private $defaultDays;
	private $meetings;
	private $events;
	private $sessions;
	private $tasks;
	private $notDisplayed = array();
	private $timeFormat;
	private $dateFormat;
	private $timezone;
	private $hide;
	
    public function __construct() {
		parent::__construct( false, 
			'TidyConnect Mini-Calendar',
			array('description'=>__('A mini calendar with all your TidyHQ events, meetings, tasks and more!', 'tidy-connect' ) )
		);
		/* Hide */
		$this->hide = !empty($_GET['hide']) ? $_GET['hide'] : '';
		/* set year */		
		$this->currentYear = !empty($_GET['cal-y']) ? $_GET['cal-y'] : date("Y");
		/* set month */
		$this->currentMonth = !empty($_GET['cal-m']) ? $_GET['cal-m'] : date("m");
		/* set day */
		$this->currentDay = date("d",time());
		/* last month/year */
		$this->lastMonth = ($this->currentMonth == 1) ? '12' : $this->currentMonth - 1;
		$this->lastMonth = sprintf('%02d',$this->lastMonth);
		$this->lastYear = ($this->currentMonth == 1) ? $this->currentYear - 1 : $this->currentYear;
		/* next month/year */
		$this->nextMonth = ($this->currentMonth == 12) ? '1' : $this->currentMonth + 1;
		$this->nextMonth = sprintf('%02d',$this->nextMonth);
		$this->nextYear = ($this->currentMonth == 12) ? $this->currentYear + 1 : $this->currentYear;
		/* THQ Connect Settings */
		//$settings = new TidyConnect();
		global $tidy_connect;
		$this->thqSettings = $tidy_connect->settings;
		//$this->thqSettings = $settings->settings;
		/* Calendar Settings */
		$this->calendarOptions = get_option('tidy_connect_calendar_settings');
		/* Set calendar colours if not defined already */
		$this->calendarOptions['event_colour'] = !empty($this->calendarOptions['event_colour']) ? $this->calendarOptions['event_colour'] : '#0b88f1';
		$this->calendarOptions['task_colour'] = !empty($this->calendarOptions['task_colour']) ? $this->calendarOptions['task_colour'] : '#66c1d2';
		$this->calendarOptions['meeting_colour'] = !empty($this->calendarOptions['meeting_colour']) ? $this->calendarOptions['meeting_colour'] : '#732e64';
		$this->calendarOptions['session_colour'] = !empty($this->calendarOptions['session_colour']) ? $this->calendarOptions['session_colour'] : '#7266D2';
		/* date/time formats */
		$this->dateFormat = get_option('date_format');
		$this->timeFormat = get_option('time_format');
		if(!empty(get_option('timezone_string'))) { $this->timezone = get_option('timezone_string'); }
		else { $this->timezone = get_option('gmt_offset') >= 0 ? 'Etc/GMT+'.get_option('gmt_offset') : 'Etc/GMT'.get_option('gmt_offset'); }
				
		/* Labels - Don't forget 0 is first. */
		$this->defaultDays = array(1=>'M',2=>'T',3=>'W',4=>'T',5=>'F',6=>'S',7=>'S'); #TODO - language support?
		/* Firsts */
		$date = mktime(0,0,0,$this->currentMonth,1,$this->currentYear);
		$this->firstDayOfMonth = date('N',$date);
		$this->firstDayOfWeek = get_option('start_of_week');
		
		if($this->firstDayOfWeek != 1) {
			$d = 1;
			$c = $this->firstDayOfWeek;
			/* days after start */
			while($c <= 7) {
				if($c == $this->firstDayOfMonth) { $this->newFirstDayOfMonth = $d; }
				if($c == $this->firstDayOfWeek) { $this->newFirstDayOfWeek = $d; }
				$this->newDays[$d] = $this->defaultDays[$c];
				$c++;
				$d++;
			} //end while 
				$c = 1;
				/* days before start */
				while($d <= 7) {
					if($c == $this->firstDayOfMonth) { $this->newFirstDayOfMonth = $d; }
					if($c == $this->firstDayOfWeek) { $this->newFirstDayOfWeek = $d; }
					$this->newDays[$d] = $this->defaultDays[$c];
					$c++;
					$d++;
				} //end while
			} //end if
			else { 
				$this->newDays = $this->defaultDays;
				$this->newFirstDayOfMonth = $this->firstDayOfMonth;
				$this->newFirstDayOfWeek = $this->firstDayOfWeek;
			} //end else
			
			if($this->newFirstDayOfWeek < $this->newFirstDayOfMonth){ //eg. Calendar starts Tues, month starts Sat
				$this->offset = $this->newFirstDayOfMonth - $this->newFirstDayOfWeek;
			} //end if
			else {
				$this->offset = 0;
			} //end else
			$this->daysInMonth = cal_days_in_month(0, $this->currentMonth, $this->currentYear);
			$this->days_in_lastmonth = cal_days_in_month(0,$this->lastMonth, $this->lastYear);
			
			/* logged in - use API */
			if(is_user_logged_in() && !empty($this->thqSettings['tidy_connect_client_secret']) && !empty($this->thqSettings['tidy_connect_client_id'])) {
				$this->events = $this->_getEvents();
				$this->meetings = $this->_getMeetings();
				//$this->sessions = $this->_getSessions();
				$this->tasks = $this->_getTasks();
			} //end if (logged in)
			/* not logged in - use iCal */
			else {
				$this->events = $this->_getPublicEvents();
				$this->meetings = $this->_getPublicMeetings();
			} //end else (not logged in)	
		} //end __construct
		
		
		public function form( $instance ) {
			$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'tidy-connect' );
		?>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'tidy-connect' ); ?></label> 
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php 
		}
	
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';

		return $instance;
	}
	
	
		public function widget($args,$instance) {
			$title = apply_filters( 'widget_title', $instance['title'] );
			$content = !empty( $title ) ? $args['before_title'] . $title . $args['after_title'] : '';
			/* Start of Calendar */
			$content .= '<div id="thq-connect-calendar-widget">';
			/* Calendar Title */
			$content .= $this->_createNavi();
			/* Start of Table */
			$content .= '<table>';
			/* Table Headers */
			$content .= '<thead><tr>';
			foreach ($this->newDays as $label) {
				$content .=  '<th title="'.$label.'">'.$label.'</th>';
			} //end foreach
			$content .=  '</tr></thead>';
			$content .= '<tfoot><tr><td colspan="7" id="thq-connect-calendar-widget-legend"><div id="toggle-cal-w-event" style="background-color: '.$this->calendarOptions['event_colour'].'" title="Hide/Show Events">E</div><div id="toggle-cal-w-meeting" style="background-color: '.$this->calendarOptions['meeting_colour'].'" title="Hide/Show Meetings">M</div>';
if(is_user_logged_in()) { $content .= '<div id="toggle-cal-w-task" style="background-color: '.$this->calendarOptions['task_colour'].'" title="Hide/Show Tasks">T</div>';
 }
$content .= '</td></tr></tfoot>';
			/* Days last month */
			$day_count = 1;
			$title = $this->days_in_lastmonth - $this->offset +1;
			while ($this->offset > 0) {
				//$title = $this->days_in_lastmonth - $this->offset;
				$content .=  '<td class="inactive"><span class="day-title">'.$title.'</span></td>';
				$this->offset = $this->offset - 1;
				$title++;
				$day_count++;
			} //end while
			/* Days this month */
			$day_num = 1;
			while ($day_num <= $this->daysInMonth) {
				$content .=  '<td id="'.$this->currentYear.'-'.$this->currentMonth.'-'.$day_num.'"';
				if ($day_num == $this->currentDay && $this->currentMonth == date('m',time()) && $this->currentYear == date('Y',time())) { $content .= ' class="today"'; }
				$content .= '><span class="day-title">'.$day_num.'</span>';
				$thisDay = $this->currentYear.'-'.$this->currentMonth.'-'.$day_num;
				$tevents = 0;
				if(!empty($this->events[$thisDay])){
					$tevents = count($this->events[$thisDay]);
					$content .= "<a href='#' title='".$tevents." events' class='cal-w-event'><span style='font-size: 10px; width: 10px; height: 10px; display: inline-block; color: ".$this->calendarOptions['event_colour']."' class='dashicons dashicons-star-filled'></span></a>";
				} //end if
				$tmeetings = 0;
				if(!empty($this->meetings[$thisDay])){
					$tmeetings = count($this->meetings[$thisDay]);
					$content .= "<a href='#' title='".$tmeetings." meetings' class='cal-w-meeting'><span style='font-size: 10px; width: 10px; height: 10px;display: inline-block; color: ".$this->calendarOptions['meeting_colour']."' class='dashicons dashicons-star-filled'></span></a>";
				} //end if
				$tevents = 0;
				if(!empty($this->tasks[$thisDay])){
					$tevents = count($this->tasks[$thisDay]);
					$content .= "<a href='#' title='".$tevents." tasks' class='cal-w-task'><span style='font-size: 10px; width: 10px; height: 10px; display: inline-block; color: ".$this->calendarOptions['task_colour']."' class='dashicons dashicons-star-filled'></span></a>";
				} //end if				
				//display events here
				$content .= '</td>';
				$day_num++;
				$day_count++;
				if ($day_count > 7) {
					$content .=  '</tr><tr>';
					$day_count = 1;
				} //end if
			} //end while
			
			/* Days next month */
			$day_num = 1;
			while ($day_count > 1 && $day_count <= 7) {
				$content .=  '<td class="inactive"><span class="day-title">'.$day_num.'</span></td>';
				$day_count++;
				$day_num++;
			} //end while
			$content .=  '</tr>';
			/* end of table */
			$content .=  '</table>';
			$content .= '</div>';
			$content .= '
			<script type="text/javascript">
					jQuery("#thq-connect-calendar-widget-legend div").click(function(){
						jQuery(this).toggleClass("inactive");
						var evType = jQuery(this).attr("id");
						var newType = evType.replace("toggle-","");
						jQuery("."+newType).toggle();
					});
			</script>
			';
			echo $args['before_widget'];
			echo __( $content, 'thq_connect_domain' );
			echo $args['after_widget'];
		} //end widget
	
	public function _createNavi(){
		$nextMonth = $this->currentMonth==12?1:intval($this->currentMonth)+1;
		$nextYear = $this->currentMonth==12?intval($this->currentYear)+1:$this->currentYear;
		$preMonth = $this->currentMonth==1?12:intval($this->currentMonth)-1;
		$preYear = $this->currentMonth==1?intval($this->currentYear)-1:$this->currentYear;
		return
			'<div><a id="thq-connect-calendar-widget-back" href="?cal-m='.$this->lastMonth.'&cal-y='.$this->lastYear.'"><</a>'.date('F Y',strtotime($this->currentYear.'-'.$this->currentMonth.'-1')).'<a id="thq-connect-calendar-widget-next" href="?cal-m='.$this->nextMonth.'&cal-y='.$this->nextYear.'">></a></div>';
    } //end _createNavi
	
	public function _getEvents() {
		//$tidy_connect = new TidyConnect();
		global $tidy_connect;
		$monthEndingDay = date('c',strtotime($this->currentYear.'-'.$this->currentMonth.'-'.$this->daysInMonth));
		$monthStartDay = date('c',strtotime($this->currentYear.'-'.$this->currentMonth.'-01'));
		/* Check if events are enabled (default) */
		if((empty($this->calendarOptions['event_flag']) || $this->calendarOptions['event_flag'] == 'enabled') && !in_array("event",$this->notDisplayed)) {
			$events = $tidy_connect->get('events',array('start_at'=>$monthStartDay,'end_at'=>$monthEndingDay));
			$allEvents = array();
			if(!empty($events) && $events != '400') {
				date_default_timezone_set($this->timezone);
				foreach($events as $event) {
					$eventDay = date("Y-m-j",strtotime($event['start_at']));
					$eventTime = date("H:i",strtotime($event['start_at']));
					$eventDesc = !empty($event['body']) ? $event['body'] : '';
					$eventSummary = !empty($event['name']) ? $event['name'] : '';
					$allEvents[$eventDay][] = array('url'=>$event['public_url'],'time'=>$eventTime,'description'=>strip_tags($eventDesc),'summary'=>$eventSummary,'nicedate'=>date($this->dateFormat,strtotime($event['start_at'])),'nicetime'=>date($this->timeFormat,strtotime($event['start_at'])));
				} //end foreach
				return $allEvents;
			} //end if
			else {
				$this->notDisplayed[] = 'event';
			} //end else
		} //end if
	} //end _getEvents
	
	public function _getSessions() {
		#####
		# I have not been able to get sessions to work correctly as it runs out of memory.
		#####
		/* For API requests */
		//$get = new TidyConnect();
		global $tidy_connect;
		/* Check if flag is disabled or not */
		if((empty($this->calendarOptions['session_flag']) || $this->calendarOptions['session_flag'] == 'enabled') && !in_array("session",$this->notDisplayed)) {
			/* Get all the sessions for processing */
			$sessions = $tidy_connect->get('sessions');
			/* Setup array for output */
			$calendarSessions = array();
			/* if positive response */
			if(!empty($sessions) && $sessions != '400') {
				/* set timezone */
				date_default_timezone_set($this->timezone);
				/* Break down all sessions to only sessions in month */
				foreach($sessions as $session) {
					$displayStartDate = mktime(0,0,0,$this->currentMonth,1,$this->currentYear);
					$displayEndDate = mktime(0,0,0,$this->currentMonth,$this->daysInMonth,$this->currentYear);
					/* Date of first occurrence */
					$sessionStartDate = strtotime($session['date_start']);
					/* date of last occurrence */
					$sessionEndDate = strtotime($session['date_end']);
					/* session must end after display starts and session must start before display month ends */
					if($sessionEndDate >= $displayStartDate && $sessionStartDate <= $displayEndDate) {
						$calendarSessions[] = $session;
					} //end if
					/* Or if the session starts and ends in the displayed month */
					elseif($sessionStartDate >= $displayStartDate && $sessionEndDate <= $displayEndDate) {
						$calendarSessions[] = $session;
					} //end elseif
					else {
					  //do nothing as the session is not in the scope
					} //end else
				} //end foreach
				$displaySessions = array();
				/* Each session in scope */
				foreach($calendarSessions as $calendarSession){
					if(!empty($calendarSession['recurrence']) && !empty($calendarSession['recurrence']['kind'])) {
						/* if recurrence has an end date or not */
						$sessionRecurrenceEnd = ((!empty($calendarSession['recurrence']['end_date'])) && ($calendarSession['recurrence']['end_date'] > $displayEndDate)) ? $displayEndDate : strtotime($calendarSession['recurrence']['end_date']);
						/* if end date is greater than calendar month */
						$sessionRecurrenceEnd = !empty($sessionRecurrenceEnd) ? $sessionRecurrenceEnd : $displayEndDate;
						/* Break down each recurrence */
						switch($calendarSession['recurrence']['kind']) {
							case 'daily':
								$sessionRecurrenceDays = '+'.$calendarSession['recurrence']['every'].' ';
								$sessionRecurrenceDays .= $calendarSession['recurrence']['every'] > 1 ? 'days' : 'day';
								$thisSession = $sessionStartDate;
								$sessionTime = date("H:i",strtotime($calendarSession['date_start']));
								$sessionDesc = $calendarSession['description'];
								$sessionTitle = $calendarSession['title'];
									while($thisSession >= $sessionRecurrenceEnd){
										$displaySessions[$thisSession][] = array('time'=>$sessionTime,'description'=>strip_tags($sessionDesc),'summary'=>$sessionTitle,'nicedate'=>date($this->dateFormat,strtotime($thisSession)),'nicetime'=>date($this->timeFormat,$thisSession));
										$thisSession = strtotime($sessionRecurrenceDays,$thisSession);
									} //end while
							break;
						/*	case 'weekly':
								$sessionRecurrenceWeeks = '+'.$session['recurrence']['every'].' ';
								$sessionRecurrenceWeeks .= $session['recurrence']['every'] > 1 ? 'weeks' : 'week';
								$sessionRecurrenceSpecificDays = !empty($session['recurrence']['week_day_numbers']) ? $session['recurrence']['week_day_numbers'] : '';
							break;
							case 'monthly':
								$sessionRecurrenceMonths = '+'.$session['recurrence']['every'].' ';
								$sessionRecurrenceMonths .= $session['recurrence']['every'] > 1 ? 'months' : 'month';
								$sessionRecurrenceSpecificDay = !empty($session['recurrence']['day_of_month']) ? $session['recurrence']['day_of_month'] : '';
								$sessionRecurrenceSpecificDays = !empty($session['recurrence']['week_day_numbers']) ? $session['recurrence']['week_day_numbers'] : '';
							break;
							case 'yearly':
								$sessionRecurrenceYears = '+'.$session['recurrence']['every'].' ';
								$sessionRecurrenceYears .= $session['recurrence']['every'] > 1 ? 'years' : 'year';
								$sessionRecurrenceSpecificDay = !empty($session['recurrence']['day_of_month']) ? $session['recurrence']['day_of_month'] : '';
								$sessionRecurrenceMonths = !empty($session['recurrence']['month']) ? $session['recurrence']['month'] : '';
								$sessionRecurrenceSpecificDays = !empty($session['recurrence']['week_day_numbers']) ? $session['recurrence']['week_day_numbers'] : '';
							break;*/
						} //end switch
					} //end if
				
				
				} //end foreach
				return $displaySessions;
			} //end if
			else {
				$this->notDisplayed[] = 'session';
			}
		} //end if
	} //end _getSessions
	
	public function _getMeetings() {
		//$get = new TidyConnect();
		global $tidy_connect;
		if((empty($this->calendarOptions['meeting_flag']) || $this->calendarOptions['meeting_flag'] == 'enabled') && !in_array("meeting",$this->notDisplayed)) {  //if meeting_flag is enabled
			$meetings = $tidy_connect->get('meetings');
			//$meetings = false;
			$calenderMeetings = array();
			if(!empty($meetings) && $meetings != '400') {
				date_default_timezone_set($this->timezone);
				foreach($meetings as $meeting) {
					$meetingDay = date("Y-m-j",strtotime($meeting['date_at']));  //Note: date_at which is different to the dev.tidyhq.com docs
					$meetingTime = date("H:i",strtotime($meeting['date_at'])); //Note: date_at which is different to the dev.tidyhq.com docs
					$meetingDesc = !empty($meeting['body']) ? $meeting['body'] : '';
					$meetingSummary = !empty($meeting['name']) ? $meeting['name'] : '';
					$calendarMeetings[$meetingDay][] = array('url'=>$meeting['public_url'],'time'=>$meetingTime,'description'=>strip_tags($meetingDesc),'summary'=>$meetingSummary,'nicedate'=>date($this->dateFormat,strtotime($meeting['date_at'])),'nicetime'=>date($this->timeFormat,strtotime($meeting['date_at'])));
				} //end foreach
				return $calendarMeetings;
			} //end if
			else {
				$this->notDisplayed[] = 'meeting';
			}
		} //end if
	} //end _getMeetings
	
	public function _getTasks() {
		$monthEndingDay = date('c',strtotime($this->currentYear.'-'.$this->currentMonth.'-'.$this->daysInMonth));
		$monthStartDay = date('c',strtotime($this->currentYear.'-'.$this->currentMonth.'-01'));
		//$get = new TidyConnect();
		global $tidy_connect;
		if((empty($this->calendarOptions['task_flag']) || $this->calendarOptions['task_flag'] == 'enabled') && !in_array("task",$this->notDisplayed)) {  //if task_flag is enabled
			$tasks = $tidy_connect->get('tasks',array('completed'=>false));
			$calenderTasks = array();
			if(!empty($tasks) && $tasks != '400') {
				date_default_timezone_set($this->timezone);
				foreach($tasks as $task) {
					$taskDay = date("Y-m-j",strtotime($task['due_date']));
					$taskTime = '';
					$taskDesc = !empty($task['description']) ? $task['description'] : '';
					$taskSummary = !empty($task['title']) ? $task['title'] : '';
					$calendarTasks[$taskDay][] = array('url'=>'https://'.$this->thqSettings['tidy_connect_domain_prefix'].'.tidyhq.com/tasks/'.$task['id'],'time'=>$taskTime,'description'=>strip_tags($taskDesc),'summary'=>$taskSummary);
				} //end foreach
				return $calendarTasks;
			} //end if
			else {
				$this->notDisplayed[] = 'task';
			} //end else
		} //end if
	} //end _getTasks
	
	public function _getPublicEvents() {
		if((empty($this->calendarOptions['event_flag']) || $this->calendarOptions['event_flag'] == 'enabled') && !in_array("event",$this->notDisplayed)) {  //if event_flag is enabled
			$ical = new TidyConnectIcal();
			$lines = $ical->load( file_get_contents( "https://".$this->thqSettings['tidy_connect_domain_prefix'].".tidyhq.com/public/schedule/events.ics" ) );
			$events = !empty($lines['VEVENT']) ? $lines['VEVENT'] : '';
			$calendarEvents = array();
			if(!empty($events)) {
				date_default_timezone_set($this->timezone);
				foreach ($events as $event) {
					$eventDay = date("Y-m-j",strtotime($event['DTSTART']));
					$eventTime = date("H:i",strtotime($event['DTSTART']));
					$calendarEvents[$eventDay][] = array('url'=>$event['URL'],'time'=>$eventTime,'description'=>strip_tags($event['DESCRIPTION']),'summary'=>$event['SUMMARY']);
				} //end foreach  
				return $calendarEvents;
			} //end if
			else {
				$this->notDisplayed[] = 'event';
			}	 //end else		
		} //end if
	} //end _getPublicEvents
	
	public function _getPublicMeetings() {
		if((empty($this->calendarOptions['meeting_flag']) || $this->calendarOptions['meeting_flag'] == 'enabled') && !in_array("meeting",$this->notDisplayed)) {  //if meeting_flag is enabled
			$ical = new TidyConnectIcal();
			$lines = $ical->load( file_get_contents( "https://".$this->thqSettings['tidy_connect_domain_prefix'].".tidyhq.com/public/schedule/meetings.ics" ) );
			$meetings = !empty($lines['VEVENT']) ? $lines['VEVENT'] : '';
			$calendarMeetings = array();
			if(!empty($meetings)) {
				date_default_timezone_set($this->timezone);
				foreach ($meetings as $meeting) {
					$meetingDay = date("Y-m-d",strtotime($meeting['DTSTART']));
					$meetingTime = date("H:i",strtotime($meeting['DTSTART']));
					$calendarMeetings[$meetingDay][] = array('url'=>$meeting['URL'],'time'=>$meetingTime,'description'=>strip_tags($meeting['DESCRIPTION']),'summary'=>$meeting['SUMMARY']);
				} //end foreach
				return $calendarMeetings;
			} //end if
			else {
				$this->notDisplayed[] = 'meeting';
			}	 //end else
		} //end if
	} //end _getPublicMeetings
	
	
} //end class
class TidyConnectCalendar {
	public $param;
	private $currentYear;
	private $currentMonth;
	private $currentDay;
	private $lastYear;
	private $lastMonth;
	private $nextYear;
	private $nextMonth;
	private $firstDayOfWeek;
	private $firstDayOfMonth;
	private $daysInMonth;
	private $days_in_lastmonth;
	private $newFirstDayOfMonth;
	private $newFirstDayOfWeek;
	private $offset;
	private $newDays;
	private $defaultDays;
	private $meetings;
	private $events;
	private $sessions;
	private $tasks;
	private $notDisplayed = array();
	private $timeFormat;
	private $dateFormat;
	private $timezone;
	private $hide;
	
    public function __construct($param = '') {
			$this->param = $param;
			/* Hide */
			$this->hide = !empty($_GET['hide']) ? $_GET['hide'] : '';
			/* set year */		
			$this->currentYear = !empty($_GET['cal-y']) ? $_GET['cal-y'] : date("Y");
			/* set month */
			$this->currentMonth = !empty($_GET['cal-m']) ? $_GET['cal-m'] : date("m");
			/* last month/year */
			$this->lastMonth = ($this->currentMonth == 1) ? '12' : $this->currentMonth - 1;
			$this->lastMonth = sprintf('%02d',$this->lastMonth);
			$this->lastYear = ($this->currentMonth == 1) ? $this->currentYear - 1 : $this->currentYear;
			/* next month/year */
			$this->nextMonth = ($this->currentMonth == 12) ? '01' : $this->currentMonth + 1;
			$this->nextMonth = sprintf('%02d',$this->nextMonth);
			$this->nextYear = ($this->currentMonth == 12) ? $this->currentYear + 1 : $this->currentYear;
			/* THQ Connect Settings */
			//$settings = new TidyConnect();
			//$this->thqSettings = $settings->settings;
			global $tidy_connect;
			$this->thqSettings = $tidy_connect->settings;
			/* Calendar Settings */
			$this->calendarOptions = get_option('tidy_connect_calendar_settings');
			/* Set calendar colours if not defined already */
			$this->calendarOptions['event_colour'] = !empty($this->calendarOptions['event_colour']) ? $this->calendarOptions['event_colour'] : '#0b88f1';
			$this->calendarOptions['task_colour'] = !empty($this->calendarOptions['task_colour']) ? $this->calendarOptions['task_colour'] : '#66c1d2';
			$this->calendarOptions['meeting_colour'] = !empty($this->calendarOptions['meeting_colour']) ? $this->calendarOptions['meeting_colour'] : '#732e64';
			$this->calendarOptions['session_colour'] = !empty($this->calendarOptions['session_colour']) ? $this->calendarOptions['session_colour'] : '#7266D2';
			/* date/time formats */
			$this->dateFormat = get_option('date_format');
			$this->timeFormat = get_option('time_format');
			/* Set timezone */
			if(!empty(get_option('timezone_string'))) { $this->timezone = get_option('timezone_string'); }
			else { $this->timezone = get_option('gmt_offset') >= 0 ? 'Etc/GMT+'.get_option('gmt_offset') : 'Etc/GMT'.get_option('gmt_offset'); }
			date_default_timezone_set($this->timezone); //change timezone temporarily for current time
			/* set today */
			$this->currentDay = date("d",time());
			date_default_timezone_set('UTC'); //set timezone back to UTC as required by WordPress
			/* Parameters for shortcode */
			if(!empty($this->param)) { //parameters are set
				foreach ($this->param as $newSetting=>$value) {
					$this->calendarOptions[$newSetting] = $value;
				} //end foreach
			} //end if
			
			/* Labels - Don't forget 0 is first. */
			$this->defaultDays = array(1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'); #TODO - language support?
			/* Firsts */
			$date = mktime(0,0,0,$this->currentMonth,1,$this->currentYear);
			$this->firstDayOfMonth = date('N',$date);
			$this->firstDayOfWeek = get_option('start_of_week');
			if($this->firstDayOfWeek == 0) { $this->firstDayOfWeek = 7; }
			if($this->firstDayOfWeek != 1) {
				$d = 1;
				$c = $this->firstDayOfWeek;
				/* days after start */
				while($c <= 7) {
					if($c == $this->firstDayOfMonth) { $this->newFirstDayOfMonth = $d; }
					if($c == $this->firstDayOfWeek) { $this->newFirstDayOfWeek = $d; }
					$this->newDays[$d] = $this->defaultDays[$c];
					$c++;
					$d++;
				} //end while 
				$c = 1;
				/* days before start */
				while($d <= 7) {
					if($c == $this->firstDayOfMonth) { $this->newFirstDayOfMonth = $d; }
					if($c == $this->firstDayOfWeek) { $this->newFirstDayOfWeek = $d; }
					$this->newDays[$d] = $this->defaultDays[$c];
					$c++;
					$d++;
				} //end while
			} //end if
			else { 
				$this->newDays = $this->defaultDays;
				$this->newFirstDayOfMonth = $this->firstDayOfMonth;
				$this->newFirstDayOfWeek = $this->firstDayOfWeek;
			} //end else
			
			if($this->newFirstDayOfWeek < $this->newFirstDayOfMonth){ //eg. Calendar starts Tues, month starts Sat
				$this->offset = $this->newFirstDayOfMonth - $this->newFirstDayOfWeek;
			} //end if
			else {
				$this->offset = 0;
			} //end else
			$this->daysInMonth = cal_days_in_month(0, $this->currentMonth, $this->currentYear);
			$this->days_in_lastmonth = cal_days_in_month(0,$this->lastMonth, $this->lastYear);
			
			/* logged in - use API */
			if(is_user_logged_in() && !empty($this->thqSettings['client_secret']) && !empty($this->thqSettings['client_id'])) {
				$this->events = $this->_getEvents();
				$this->meetings = $this->_getMeetings();
				//$this->sessions = $this->_getSessions();
				$this->tasks = $this->_getTasks();
			} //end if (logged in)
			/* not logged in - use iCal */
			else {
				$this->events = $this->_getPublicEvents();
				$this->meetings = $this->_getPublicMeetings();
			} //end else (not logged in)
			/* Start of Calendar */
			$content = '<div id="thq-connect-calendar-wrap">';
	    		
	    		/* Allow create when user is logged in */
	    		if (is_user_logged_in()) {
					$content .= "<div class='thq-connect-calendar-add-dropdown'><button class='thq-connect-calendar-add-dropbtn'><span class='dashicons dashicons-plus'></span> New <span class='dashicons dashicons-arrow-down-alt2'></span></button><div class='thq-connect-calendar-add-dropdown-content'>";
					
					if( !isset( $this->calendarOptions['event_flag'] ) ) {
						$content .= "<a href='https://" . $this->thqSettings['tidy_connect_domain_prefix'] . ".tidyhq.com/schedule/events/new'>New Event</a>";						}
					if( !isset( $this->calendarOptions['task_flag'] ) ) { 
    					$content .= "<a href='https://" . $this->thqSettings['tidy_connect_domain_prefix'] . ".tidyhq.com/tasks/new'>New Task</a>";
    				}
					if( !isset( $this->calendarOptions['meeting_flag'] ) ) { 
						$content .= "<a href='https://" . $this->thqSettings['tidy_connect_domain_prefix'] . ".tidyhq.com/schedule/meetings/new'>New Meeting</a>";
					}
					if( !isset( $this->calendarOptions['session_flag'] ) ) { 
						$content .= "<a href='https://" . $this->thqSettings['tidy_connect_domain_prefix'] . ".tidyhq.com/sessions/new'>New Session</a>";
					}
  					$content .= "</div></div>";
			}
			/* Calendar Title */
			$content .= $this->_createNavi();
			/* Start of Table */
			$content .= '<table id="thq-connect-calendar-table">';
			/* Table Headers */
			$content .= '<thead><tr>';
			foreach ($this->newDays as $label) {
				$content .=  '<th>'.$label.'</th>';	
			} //end foreach
			$content .=  '</tr></thead>';
			
			/* Days last month */
			$day_count = 1;
			$title = $this->days_in_lastmonth - $this->offset +1;
			while ($this->offset > 0) {
				//$title = $days_in_lastmonth - $this->offset;
				$content .=  '<td class="inactive"><span class="day-title">'.$title.'</span></td>';
				$this->offset = $this->offset - 1;
				$title++;
				$day_count++;
			} //end while
			/* Days this month */
			$day_num = 1;
			while ($day_num <= $this->daysInMonth) {
				$content .=  '<td id="'.$this->currentYear.'-'.$this->currentMonth.'-'.$day_num.'"';
				if ($day_num == $this->currentDay && $this->currentMonth == date('n',time()) && $this->currentYear == date('Y',time())) { $content .= ' class="active"'; }
				$content .= '><span class="day-title">'.$day_num.'</span>';
				$thisDay = $this->currentYear.'-'.$this->currentMonth.'-'.$day_num;
				if(!empty($this->events[$thisDay])){
					foreach ($this->events[$thisDay] as $event) {
						$content .= '<a title="'.$event['summary'].': '.$event['description'].'" class="calendar-event" href="'.$event['url'].'" style="background-color: '.$this->calendarOptions['event_colour'].'">';
						if(!empty($event['nicetime'])) { $content .= '<span class="calendar-event-time">'.$event['nicetime'].'</span> '; }
						$content .= '<span class="calendar-event-title">'.$event['summary'].'</span>';
						$content .= '</a>';
					} //end foreach
				} //end if
				if(!empty($this->meetings[$thisDay])){
					foreach ($this->meetings[$thisDay] as $event) {
						$content .= '<a title="'.$event['summary'].': '.$event['description'].'" class="calendar-meeting" href="'.$event['url'].'" style="background-color: '.$this->calendarOptions['meeting_colour'].'">';
						if(!empty($event['nicetime'])) { $content .= '<span class="calendar-event-time">'.$event['nicetime'].'</span> '; }
						$content .= '<span class="calendar-event-title">'.$event['summary'].'</span>';
						$content .= '</a>';
					} //end foreach
				} //end if
				if(!empty($this->sessions[$thisDay])){
					foreach ($this->sessions[$thisDay] as $event) {
						$content .= '<a title="'.$event['summary'].': '.$event['description'].'" class="calendar-session" href="'.$event['url'].'" style="background-color: '.$this->calendarOptions['meeting_colour'].'">';
						if(!empty($event['nicetime'])) { $content .= '<span class="calendar-event-time">'.$event['nicetime'].'</span> '; }
						$content .= '<span class="calendar-event-title">'.$event['summary'].'</span>';
						$content .= '</a>';
					} //end foreach
				} //end if
				if(!empty($this->sessions[$thisDay])){
					foreach ($this->sessions[$thisDay] as $event) {
						$content .= '<a title="'.$event['summary'].': '.$event['description'].'" class="calendar-event" href="'.$event['url'].'" style="background-color: '.$this->calendarOptions['meeting_colour'].'">';
						if(!empty($event['nicetime'])) { $content .= '<span class="calendar-event-time">'.$event['nicetime'].'</span> '; }
						$content .= '<span class="calendar-event-title">'.$event['summary'].'</span>';
						$content .= '</a>';
					} //end foreach
				} //end if
				if(!empty($this->tasks[$thisDay])){
					foreach ($this->tasks[$thisDay] as $event) {
						$content .= '<a title="'.$event['summary'].': '.$event['description'].'" class="calendar-task" href="'.$event['url'].'" style="background-color: '.$this->calendarOptions['task_colour'].'">';
						$content .= '<span class="calendar-event-title">'.$event['summary'].'</span>';
						$content .= '</a>';
					} //end foreach
				} //end if
				//display events here
				$content .= '</td>';
				$day_num++;
				$day_count++;
				if ($day_count > 7) {
					$content .=  '</tr><tr>';
					$day_count = 1;
				} //end if
			} //end while
			
			/* Days next month */
			$day_num = 1;
			while ($day_count > 1 && $day_count <= 7) {
				$content .=  '<td class="inactive"><span class="day-title">'.$day_num.'</span></td>';
				$day_count++;
				$day_num++;
			} //end while
			$content .=  '</tr>';
			/* end of table */
			$content .=  '</table>';
			$content .= '<div id="thq-connect-calendar-legend">';
			if( !isset( $this->calendarOptions['event_flag'] ) ) { $content .= '<span id="toggle-calendar-event" style="background-color: '.$this->calendarOptions['event_colour'].'">Events</span>'; }
			if( !isset( $this->calendarOptions['meeting_flag'] ) ) { $content .= '<span  id="toggle-calendar-meeting" style="background-color: '.$this->calendarOptions['meeting_colour'].'">Meetings</span>'; }
			if( is_user_logged_in() && !isset( $this->calendarOptions['session_flag'] ) ) {
				$content .= '<span id="toggle-calendar-session" style="background-color: '.$this->calendarOptions['session_colour'].'">Sessions</span>';
			}
			if( is_user_logged_in() && !isset( $this->calendarOptions['task_flag'] ) ) {
				$content .= '<span id="toggle-calendar-task" style="background-color: '.$this->calendarOptions['task_colour'].'">Tasks</span>';
			}
			$content .= '</div>';
			/* Show note if something is not displayed */
			if(!empty($this->notDisplayed)) {
				$content .= "<p><i>Note: You may need to be logged in to display additional calendar options.  ";
				$notDisplayedCount = count($this->notDisplayed);
				$i = 0;
				foreach($this->notDisplayed as $notDisplayedType) {
					$content .= ucfirst($notDisplayedType)."s";
					$i++;
					if($i == $notDisplayedCount-1) { $content .= " and "; }
					elseif($i < $notDisplayedCount) { $content .= ", "; }
				}//end foreach
				$content .= " are not displayed on this calendar.</i></p>";
			} //end if
			$content .= '</div>';
			$content .= '
			<script type="text/javascript">
					jQuery("#thq-connect-calendar-legend span").click(function(){
						jQuery(this).toggleClass("inactive");
						var evType = jQuery(this).attr("id");
						var newType = evType.replace("toggle-","");
						jQuery("."+newType).toggle();
					});
			</script>
			';
			echo $content;
		} //end __construct
	
	public function _createNavi(){
		$nextMonth = $this->currentMonth==12?1:intval($this->currentMonth)+1;
		$nextYear = $this->currentMonth==12?intval($this->currentYear)+1:$this->currentYear;
		$preMonth = $this->currentMonth==1?12:intval($this->currentMonth)-1;
		$preYear = $this->currentMonth==1?intval($this->currentYear)-1:$this->currentYear;
		return
			'<div class="calendar-title"><div class="calendar-left-nav"><a class="calendar-button calendar-previous" href="?cal-m='.$this->lastMonth.'&cal-y='.$this->lastYear.'"><span class="calendar-arrow">&#10094; Last Month</span></a></div><h2>'.date('F Y',strtotime($this->currentYear.'-'.$this->currentMonth.'-1')).'</h2><div class="calendar-right-nav"><a class="calendar-button calendar-next" href="?cal-m='.$this->nextMonth.'&cal-y='.$this->nextYear.'"><span class="calendar-arrow">Next Month &#10095;</span></a></div></div>';
    } //end _createNavi
	
	public function _getEvents() {
		//$get = new TidyConnect();
		global $tidy_connect;
		$monthEndingDay = date('c',strtotime($this->currentYear.'-'.$this->currentMonth.'-'.$this->daysInMonth));
		$monthStartDay = date('c',strtotime($this->currentYear.'-'.$this->currentMonth.'-01'));
		/* Check if events are enabled (default) */
		if((empty($this->calendarOptions['event_flag']) || $this->calendarOptions['event_flag'] == 'enabled') && !in_array("event",$this->notDisplayed)) {
			$events = $tidy_connect->get('events',array('start_at'=>$monthStartDay,'end_at'=>$monthEndingDay));
			$allEvents = array();
			if(!empty($events) && $events != '400') {
				date_default_timezone_set($this->timezone);
				foreach($events as $event) {
					$eventDay = date("Y-m-j",strtotime($event['start_at']));
					$eventTime = date("H:i",strtotime($event['start_at']));
					$eventDesc = !empty($event['body']) ? $event['body'] : '';
					$eventSummary = !empty($event['name']) ? $event['name'] : '';
					$allEvents[$eventDay][] = array('url'=>$event['public_url'],'time'=>$eventTime,'description'=>strip_tags($eventDesc),'summary'=>$eventSummary,'nicedate'=>date($this->dateFormat,strtotime($event['start_at'])),'nicetime'=>date($this->timeFormat,strtotime($event['start_at'])));
				} //end foreach
				return $allEvents;
			} //end if
			else {
				$this->notDisplayed[] = 'event';
			} //end else
		} //end if
	} //end _getEvents
	
	public function _getSessions() {
		#####
		# I have not been able to get sessions to work correctly as it runs out of memory.
		#####
		/* For API requests */
		//$get = new TidyConnect();
		global $tidy_connect;
		/* Check if flag is disabled or not */
		if((empty($this->calendarOptions['session_flag']) || $this->calendarOptions['session_flag'] == 'enabled') && !in_array("session",$this->notDisplayed)) {
			/* Get all the sessions for processing */
			$sessions = $tidy_connect->get('sessions');
			/* Setup array for output */
			$calendarSessions = array();
			/* if positive response */
			if(!empty($sessions) && $sessions != '400') {
				/* set timezone */
				date_default_timezone_set($this->timezone);
				/* Break down all sessions to only sessions in month */
				foreach($sessions as $session) {
					$displayStartDate = mktime(0,0,0,$this->currentMonth,1,$this->currentYear);
					$displayEndDate = mktime(0,0,0,$this->currentMonth,$this->daysInMonth,$this->currentYear);
					/* Date of first occurrence */
					$sessionStartDate = strtotime($session['date_start']);
					/* date of last occurrence */
					$sessionEndDate = strtotime($session['date_end']);
					/* session must end after display starts and session must start before display month ends */
					if($sessionEndDate >= $displayStartDate && $sessionStartDate <= $displayEndDate) {
						$calendarSessions[] = $session;
					} //end if
					/* Or if the session starts and ends in the displayed month */
					elseif($sessionStartDate >= $displayStartDate && $sessionEndDate <= $displayEndDate) {
						$calendarSessions[] = $session;
					} //end elseif
					else {
					  //do nothing as the session is not in the scope
					} //end else
				} //end foreach
				$displaySessions = array();
				/* Each session in scope */
				foreach($calendarSessions as $calendarSession){
					if(!empty($calendarSession['recurrence']) && !empty($calendarSession['recurrence']['kind'])) {
						/* if recurrence has an end date or not */
						$sessionRecurrenceEnd = ((!empty($calendarSession['recurrence']['end_date'])) && ($calendarSession['recurrence']['end_date'] > $displayEndDate)) ? $displayEndDate : strtotime($calendarSession['recurrence']['end_date']);
						/* if end date is greater than calendar month */
						$sessionRecurrenceEnd = !empty($sessionRecurrenceEnd) ? $sessionRecurrenceEnd : $displayEndDate;
						/* Break down each recurrence */
						switch($calendarSession['recurrence']['kind']) {
							case 'daily':
								$sessionRecurrenceDays = '+'.$calendarSession['recurrence']['every'].' ';
								$sessionRecurrenceDays .= $calendarSession['recurrence']['every'] > 1 ? 'days' : 'day';
								$thisSession = $sessionStartDate;
								$sessionTime = date("H:i",strtotime($calendarSession['date_start']));
								$sessionDesc = $calendarSession['description'];
								$sessionTitle = $calendarSession['title'];
									while($thisSession >= $sessionRecurrenceEnd){
										$displaySessions[$thisSession][] = array('time'=>$sessionTime,'description'=>strip_tags($sessionDesc),'summary'=>$sessionTitle,'nicedate'=>date($this->dateFormat,strtotime($thisSession)),'nicetime'=>date($this->timeFormat,$thisSession));
										$thisSession = strtotime($sessionRecurrenceDays,$thisSession);
									} //end while
							break;
						/*	case 'weekly':
								$sessionRecurrenceWeeks = '+'.$session['recurrence']['every'].' ';
								$sessionRecurrenceWeeks .= $session['recurrence']['every'] > 1 ? 'weeks' : 'week';
								$sessionRecurrenceSpecificDays = !empty($session['recurrence']['week_day_numbers']) ? $session['recurrence']['week_day_numbers'] : '';
							break;
							case 'monthly':
								$sessionRecurrenceMonths = '+'.$session['recurrence']['every'].' ';
								$sessionRecurrenceMonths .= $session['recurrence']['every'] > 1 ? 'months' : 'month';
								$sessionRecurrenceSpecificDay = !empty($session['recurrence']['day_of_month']) ? $session['recurrence']['day_of_month'] : '';
								$sessionRecurrenceSpecificDays = !empty($session['recurrence']['week_day_numbers']) ? $session['recurrence']['week_day_numbers'] : '';
							break;
							case 'yearly':
								$sessionRecurrenceYears = '+'.$session['recurrence']['every'].' ';
								$sessionRecurrenceYears .= $session['recurrence']['every'] > 1 ? 'years' : 'year';
								$sessionRecurrenceSpecificDay = !empty($session['recurrence']['day_of_month']) ? $session['recurrence']['day_of_month'] : '';
								$sessionRecurrenceMonths = !empty($session['recurrence']['month']) ? $session['recurrence']['month'] : '';
								$sessionRecurrenceSpecificDays = !empty($session['recurrence']['week_day_numbers']) ? $session['recurrence']['week_day_numbers'] : '';
							break;*/
						} //end switch
					} //end if
				
				
				} //end foreach
				return $displaySessions;
			} //end if
			else {
				$this->notDisplayed[] = 'session';
			}
		} //end if
	} //end _getSessions
	
	public function _getMeetings() {
		//$get = new TidyConnect();
		global $tidy_connect;
		if((empty($this->calendarOptions['meeting_flag']) || $this->calendarOptions['meeting_flag'] == 'enabled') && !in_array("meeting",$this->notDisplayed)) {  //if meeting_flag is enabled
			$meetings = $tidy_connect->get('meetings');
			//$meetings = false;
			$calenderMeetings = array();
			if(!empty($meetings) && $meetings != '400') {
				date_default_timezone_set($this->timezone);
				foreach($meetings as $meeting) {
					$meetingDay = date("Y-m-j",strtotime($meeting['date_at']));  //Note: date_at which is different to the dev.tidyhq.com docs
					$meetingTime = date("H:i",strtotime($meeting['date_at'])); //Note: date_at which is different to the dev.tidyhq.com docs
					$meetingDesc = !empty($meeting['body']) ? $meeting['body'] : '';
					$meetingSummary = !empty($meeting['name']) ? $meeting['name'] : '';
					$calendarMeetings[$meetingDay][] = array('url'=>$meeting['public_url'],'time'=>$meetingTime,'description'=>strip_tags($meetingDesc),'summary'=>$meetingSummary,'nicedate'=>date($this->dateFormat,strtotime($meeting['date_at'])),'nicetime'=>date($this->timeFormat,strtotime($meeting['date_at'])));
				} //end foreach
				return $calendarMeetings;
			} //end if
			else {
				$this->notDisplayed[] = 'meeting';
			}
		} //end if
	} //end _getMeetings
	
	public function _getTasks() {
		//$get = new TidyConnect();
		global $tidy_connect;
		if((empty($this->calendarOptions['task_flag']) || $this->calendarOptions['task_flag'] == 'enabled') && !in_array("task",$this->notDisplayed)) {  //if task_flag is enabled
			$tasks = $tidy_connect->get('tasks',array('completed'=>false));
			$calenderTasks = array();
			if(!empty($tasks) && $tasks != '400') {
				date_default_timezone_set($this->timezone);
				foreach($tasks as $task) {
					$taskDay = date("Y-m-j",strtotime($task['due_date']));
					$taskTime = '';
					$taskDesc = !empty($task['description']) ? $task['description'] : '';
					$taskSummary = !empty($task['title']) ? $task['title'] : '';
					$calendarTasks[$taskDay][] = array('url'=>'https://'.$this->thqSettings['domain_prefix'].'.tidyhq.com/tasks/'.$task['id'],'time'=>$taskTime,'description'=>strip_tags($taskDesc),'summary'=>$taskSummary);
				} //end foreach
				return $calendarTasks;
			} //end if
			else {
				$this->notDisplayed[] = 'task';
			} //end else
		} //end if
	} //end _getTasks
	
	public function _getPublicEvents() {
		if((empty($this->calendarOptions['event_flag']) || $this->calendarOptions['event_flag'] == 'enabled') && !in_array("event",$this->notDisplayed)) {  //if event_flag is enabled
			$ical = new TidyConnectIcal();
			$lines = $ical->load( file_get_contents( "https://".$this->thqSettings['tidy_connect_domain_prefix'].".tidyhq.com/public/schedule/events.ics" ) );
			//$events = $lines['VEVENT'];
			$events = !empty($lines['VEVENT']) ? $lines['VEVENT'] : '';
			$calendarEvents = array();
			if(!empty($events)) {
				date_default_timezone_set($this->timezone);
				foreach ($events as $event) {
					$eventDay = date("Y-m-j",strtotime($event['DTSTART']));
					$eventTime = date("H:i",strtotime($event['DTSTART']));
					$calendarEvents[$eventDay][] = array('url'=>$event['URL'],'time'=>$eventTime,'description'=>strip_tags($event['DESCRIPTION']),'summary'=>$event['SUMMARY']);
				} //end foreach  
				return $calendarEvents;
			} //end if
			else {
				$this->notDisplayed[] = 'event';
			}	 //end else		
		} //end if
	} //end _getPublicEvents
	
	public function _getPublicMeetings() {
		if((empty($this->calendarOptions['meeting_flag']) || $this->calendarOptions['meeting_flag'] == 'enabled') && !in_array("meeting",$this->notDisplayed)) {  //if meeting_flag is enabled
			$ical = new TidyConnectIcal();
			$lines = $ical->load( file_get_contents( "https://".$this->thqSettings['tidy_connect_domain_prefix'].".tidyhq.com/public/schedule/meetings.ics" ) );
			$meetings = !empty($lines['VEVENT']) ? $lines['VEVENT'] : '';
			$calendarMeetings = array();
			if(!empty($meetings)) {
				date_default_timezone_set($this->timezone);
				foreach ($meetings as $meeting) {
					$meetingDay = date("Y-n-d",strtotime($meeting['DTSTART']));
					$meetingTime = date("H:i",strtotime($meeting['DTSTART']));
					$calendarMeetings[$meetingDay][] = array('url'=>$meeting['URL'],'time'=>$meetingTime,'description'=>strip_tags($meeting['DESCRIPTION']),'summary'=>$meeting['SUMMARY']);
				} //end foreach
				return $calendarMeetings;
			} //end if
			else {
				$this->notDisplayed[] = 'meeting';
			}	 //end else
		} //end if
	} //end _getPublicMeetings
	
	
} //end class
/**
* Class to read iCal
*/
class TidyConnectIcal
{
	private $ical = null;
	private $_lastitem = null;
	public function &load($data)
	{
		$this->ical = false;
		$regex_opt = 'mib';
		// Lines in the string
		$lines = mb_split( '[\r\n]+', $data );
		// Delete empty ones
		$last = count( $lines );
		for($i = 0; $i < $last; $i ++)
		{
			if (trim( $lines[$i] ) == '')
				unset( $lines[$i] );
		}
		$lines = array_values( $lines );
		// First and last items
		$first = 0;
		$last = count( $lines ) - 1;
		if (! ( mb_ereg_match( '^BEGIN:VCALENDAR', $lines[$first], $regex_opt ) and mb_ereg_match( '^END:VCALENDAR', $lines[$last], $regex_opt ) ))
		{
			$first = null;
			$last = null;
			foreach ( $lines as $i => &$line )
			{
				if (mb_ereg_match( '^BEGIN:VCALENDAR', $line, $regex_opt ))
					$first = $i;
				if (mb_ereg_match( '^END:VCALENDAR', $line, $regex_opt ))
				{
					$last = $i;
					break;
				}
			}
		}
		// Procesing
		if (! is_null( $first ) and ! is_null( $last ))
		{
			$lines = array_slice( $lines, $first + 1, ( $last - $first - 1 ), true );
			$group = null;
			$parentgroup = null;
			$this->ical = [];
			$addTo = [];
			$addToElement = null;
			foreach ( $lines as $line )
			{
				$clave = null;
				$pattern = '^(BEGIN|END)\:(.+)$'; // (VALARM|VTODO|VJOURNAL|VEVENT|VFREEBUSY|VCALENDAR|DAYLIGHT|VTIMEZONE|STANDARD)
				mb_ereg_search_init( $line );
				$regs = mb_ereg_search_regs( $pattern, $regex_opt );
				if ($regs)
				{
					// $regs
					// 0 => BEGIN:VEVENT
					// 1 => BEGIN
					// 2 => VEVENT
					switch ( $regs[1] )
					{
						case 'BEGIN' :
							if (! is_null( $group ))
								$parentgroup = $group;
							$group = trim( $regs[2] );
							// Adding new values to groups
							if (is_null( $parentgroup ))
							{
								if (! array_key_exists( $group, $this->ical ))
									$this->ical[$group] = [null];
								else
									$this->ical[$group][] = null;
							}
							else
							{
								if (! array_key_exists( $parentgroup, $this->ical ))
									$this->ical[$parentgroup] = [$group => [null]];
								if (! array_key_exists( $group, $this->ical[$parentgroup] ))
									$this->ical[$parentgroup][$group] = [null];
								else
									$this->ical[$parentgroup][$group][] = null;
							}
							break;
						case 'END' :
							if (is_null( $group ))
								$parentgroup = null;
							$group = null;
							break;
					}
					continue;
				}
				if (! in_array( $line[0], [" ", "\t"] ))
					$this->addItem( $line, $group, $parentgroup );
				else
					$this->concatItem( $line );
			}
		}
		return $this->ical;
	}
	public function addType(&$value, $item)
	{
		$type = explode( '=', $item );
		if (count( $type ) > 1 and $type[0] == 'VALUE')
			$value['type'] = $type[1];
		else
			$value[$type[0]] = $type[1];
		return $value;
	}
	public function addItem($line, $group, $parentgroup)
	{
		$line = $this->transformLine( $line );
		$item = explode( ':', $line, 2 );
		// If $group is null is an independent value
		if (is_null( $group ))
		{
			$this->ical[$item[0]] = ( count( $item ) > 1 ? $item[1] : null );
			$this->_lastitem = &$this->ical[$item[0]];
		}
		// If $group is set then is an item of a group
		else
		{
			$subitem = explode( ';', $item[0], 2 );
			if (count( $subitem ) == 1)
				$value = ( count( $item ) > 1 ? $item[1] : null );
			else
			{
				$value = ['value' => $item[1]];
				$this->addType( $value, $subitem[1] );
			}
			// Multi value
			if (is_string( $value ))
			{
				$z = explode( ';', $value );
				if (count( $z ) > 1)
				{
					$value = [];
					foreach ( $z as &$v )
					{
						$t = explode( '=', $v );
						$value[$t[0]] = $t[count( $t ) - 1];
					}
				}
				unset( $z );
			}
			if (is_null( $parentgroup ))
			{
				$this->ical[$group][count( $this->ical[$group] ) - 1][$subitem[0]] = $value;
				$this->_lastitem = &$this->ical[$group][count( $this->ical[$group] ) - 1][$subitem[0]];
			}
			else
			{
				$this->ical[$parentgroup][$group][count( $this->ical[$parentgroup][$group] ) - 1][$subitem[0]] = $value;
				$this->_lastitem = &$this->ical[$parentgroup][$group][count( $this->ical[$parentgroup][$group] ) - 1][$subitem[0]];
			}
		}
	}
	public function concatItem($line)
	{
		$line = mb_substr( $line, 1 );
		if (is_array( $this->_lastitem ))
		{
			$line = $this->transformLine( $this->_lastitem['value'] . $line );
			$this->_lastitem['value'] = $line;
		}
		else
		{
			$line = $this->transformLine( $this->_lastitem . $line );
			$this->_lastitem = $line;
		}
	}
	public function transformLine($line)
	{
		$patterns = ['\\\\[n]', '\\\\[t]', '\\\\,', '\\\\;'];
		$replacements = ["\n", "\t", ",", ";"];
		return $this->mb_eregi_replace_all( $patterns, $replacements, $line );
	}
	public function mb_eregi_replace_all($pattern, $replacement, $string)
	{
		if (is_array( $pattern ) and is_array( $replacement ))
		{
			foreach ( $pattern as $i => $patron )
			{
				if (array_key_exists( $i, $replacement ))
					$reemplazo = $replacement[$i];
				else
					$reemplazo = '';
				$string = mb_eregi_replace( $patron, $reemplazo, $string );
			}
		}
		elseif (is_string( $pattern ) and is_string( $replacement ))
			$string = mb_eregi_replace( $pattern, $replacement, $string );
		return $string;
	}
} //end class


/* Function called by shortcode */
function tidy_connect_calendar_function( $atts ) {
	$content = new TidyConnectCalendar( $atts );
} //end function

function tidy_connect_calendar_widget_function() {
  register_widget( 'TidyConnectCalendarWidget' );
} 

function tidy_connect_calendar_scripts() {
	wp_enqueue_style( 'tidy-connect-calendar-css', plugin_dir_url( __FILE__ ) . 'style.css', '', '1.0.0' ); //Calendar styles
}

add_shortcode( 'tidy_connect_calendar', 'tidy_connect_calendar_function' ); //Calendar shortcode
add_action( 'wp_enqueue_scripts', 'tidy_connect_calendar_scripts');
add_action( 'widgets_init', 'tidy_connect_calendar_widget_function' );
?>
