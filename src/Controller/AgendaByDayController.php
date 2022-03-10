<?php

namespace Drupal\gm_agenda\Controller;

use Drupal\Core\Controller\ControllerBase;
// use Symfony\Component\EventDispatcher\EventDispatcherInterface;
// use Symfony\Component\HttpFoundation\JsonResponse;
// use Symfony\Component\HttpFoundation\Request;
// use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
// use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
// use Drupal\Core\Access\AccessResult;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Component\Utility\SortArray;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller class for requests from the smart buttons.
 */
class AgendaByDayController extends ControllerBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;


  /**
   *
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messager interface
   */
  public function __construct(LoggerInterface $logger, AccountProxyInterface $current_user, EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('logger.factory')->get('gm_agenda'),
        $container->get('current_user'),
        $container->get('entity_type.manager'),
        $container->get('messenger'),
    );
  }

  /**
   * Hello.
   * OLD -----------
   *
   */
  public function pageAgenda() {

    // Format element for Date request
    $timezone = date_default_timezone_get();
    $now = new \DateTime('now', new \DateTimeZone($timezone));
    $date_format = $now->format('Y-m-d');

    // Prepare the current language
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Prepare query variables
    $q_page = (\Drupal::request()->get('page')) ? (int)\Drupal::request()->get('page') : 0;

    // $queryBetween = \Drupal::entityQuery('node')
    //   ->condition('status', 1)
    //   ->condition('type', 'evenement')
    //   ->condition('langcode', $language)
    //   ->condition('field_date_debut', array('2021-06-01T08:00:00', '2021-07-01T08:00:00'), 'BETWEEN')
    //   ->sort('field_date_debut', 'ASC')
    //   ->range(0, 20);
    //   $queryBetweenNids = $queryBetween->execute();

    // $nodes_by_month = $this->entityTypeManager->getStorage('node')->loadMultiple($queryBetweenNids);
    // foreach ($nodes_by_month as $ky => $month) {
    // }

    // $current_month =  \Drupal::service('date.formatter')->format(time(), 'custom', 'n');
    // $current_year =  \Drupal::service('date.formatter')->format(time(), 'custom', 'Y');

    // ksm($current_month);
    // ksm($current_year);
    // ksm($this->daysInMonth(CAL_GREGORIAN, 7, 2022));

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'evenement')
      ->condition('langcode', $language)
      ->sort('field_date_debut', 'ASC')
      ->range(0, 20);

    $orGroup = $query->orConditionGroup()
      ->condition('field_date_debut', $date_format, '>=')
      ->condition('field_date_fin', $date_format, '>=');
      
    // Add the group to the query.
    $query->condition($orGroup);
    $nids = $query->execute();


    // $entity_type_manager = \Drupal::entityTypeManager();
    $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
    $view_mode = 'teaser2';
    // Load all event
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $list = [];
    // $order = [];
    // $day = [];
    $lp = 0;
    $lpel = 0;
    $Layout = 0;
    $date_to_compare = NULL;
    foreach ($nodes as $node) {
      $date_order = [];
      // Get stat date for groupin by day
      $date_debut = ($node->hasField('field_date_debut') && !$node->get('field_date_debut')->isEmpty()) ? $node->get('field_date_debut')->getValue()[0]['value'] : NULL;
      // Start loop grouping by day
      if ($lp == 0) {
        // Store the first date to compare
        $date = new DrupalDateTime($date_debut, 'UTC');
        $date_to_compare = (int)\Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'd');
        $formatted_m =  \Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'M', date_default_timezone_get());
        $formatted_y = (int)\Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'Y');

        // $result = \Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'M', date_default_timezone_get());

        $list[$Layout]['date'] = ['d' => $date_to_compare, 'm' => $formatted_m, 'y' => $formatted_y];
        $list[$Layout]['depth'] = '0';
        $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
        $lpel++;
      }else {
        $date = new DrupalDateTime($date_debut, 'UTC');
        $formatted_date = (int)\Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'd');
        // If the date is the same
        if ($formatted_date == $date_to_compare) {
          // $list[$Layout]['depth'] = '1';
          $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
          $lpel++;
          
          // $order[$lp] = '1';
          // $list[$lp] = $node_view_builder->view($node, $view_mode_same_day);
        }else{
          $formatted_m =  \Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'M', date_default_timezone_get());
          $formatted_y = (int)\Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'Y');
          $Layout++;
          $lpel = 0;
          $list[$Layout]['date'] = ['d' => $formatted_date, 'm' => $formatted_m, 'y' => $formatted_y];
          $list[$Layout]['depth'] = '0';
          $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
          $lpel++;
          // Restart compared date
          $date_to_compare = $formatted_date;
        }
      }
      $lp++;
    }

    return [
      '#theme' => 'gm_agenda_list',
      '#node_list' => $list,
      // '#order' => $order,
      // '#day' => $day,
    ];
  }

  /**
   * pageAgendaByMonth()
   * 
   * @param string $month
   *  The month
   * @param string $year
   *  The year
   * @param string $regions
   *  The regions to search
   * @return mixed
   *  The data
   */
  public function pageAgendaByMonth($month = NULL, $year = 2018, $regions = NULL) {
    // $is_today = FALSE;
    $route_name = \Drupal::routeMatch()->getRouteName();

    $request_params = $this->buildCurrentDataParameters($month, $year);

    if (!$request_params || !is_array($request_params)|| empty($request_params)) {
      return [
        '#theme' => 'gm_agenda_by_month',
        '#node_list' => [],
        '#prev_data' => NULL,
        '#next_data' => NULL,
        '#current_data' => NULL,
        '#pager_data' => NULL,
        '#filter_regions' => NULL,
        '#event_today' => [],
        '#is_today' => NULL
      ];
    }

    // Prepare the current language
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    // Prepare query variables
    $q_page = (\Drupal::request()->get('page')) ? (int)\Drupal::request()->get('page') : 0;
    $q_items_per_page = (\Drupal::request()->get('ipp')) ? (int)\Drupal::request()->get('ipp') : 10;

    $list = NULL;
    $find_term = 0;
    $find_regions_tid = NULL;
    $info_filter_regions = [];
    // Check if regions
    if ($regions && is_string($regions)) {
      $find_regions_tid = $this->getTidByName('/regions/'.$regions);
    }

    // Find events for today
    $q_total_items = $this->buildCountRequest($request_params['search_end_month_more_days'], $request_params['search_start_month'], $request_params['search_end_month'], $find_regions_tid, NULL, $language, $request_params['is_periode'], $request_params['is_reel']);
    // $q_total_items = 5;
    if ((int)$q_total_items > 0) {
      $result_select = $this->buildDataRequest(
        $request_params['search_end_month_more_days'],
        $request_params['search_start_month'],
        $request_params['search_end_month'],
        $find_regions_tid,
        NULL,
        $language,
        $q_page,
        $q_items_per_page,
        $request_params['is_periode'],
        $request_params['is_reel'],
      );
      $node_array_to_build = [];
      if (is_array($result_select)) {
        foreach ($result_select as $r => $nd) {
          $date_now_start = new DrupalDateTime($nd->field_date_value, 'T');
          $date_now_end = new DrupalDateTime($nd->field_date_value, 'T');

          if ($request_params['is_periode'] == 'upcoming') {
            if ($date_now_start->format('m') == $request_params['month'] && $date_now_start->format('Y-m-d') >= $request_params['now']) {
              $node_array_to_build[] = ['nid' => $nd->nid, 'date' => $nd->field_date_value];
            }else{
              $node_array_to_build[] = ['nid' => $nd->nid, 'date' => $nd->field_date_end_value];
            }
          }elseif ($request_params['is_periode'] == 'today') {
            if ($date_now_start->format('Y-m-d') != $request_params['now']) {
              $node_array_to_build[] = ['nid' => $nd->nid, 'date' => $nd->field_date_end_value];
            }else{
              $node_array_to_build[] = ['nid' => $nd->nid, 'date' => $nd->field_date_value];
            }
          }else{
            if ($date_now_start->format('m') == $request_params['month'] && $date_now_start->format('Y-m-dTH:m:s') >= $request_params['search_start_month']) {
              $node_array_to_build[] = ['nid' => $nd->nid, 'date' => $nd->field_date_value];
            }else{
              $node_array_to_build[] = ['nid' => $nd->nid, 'date' => $nd->field_date_end_value];
            }
          } 
        }
        // Array by date start date reorganised
        usort($node_array_to_build, function($a, $b) {
          return $this->sortByDateStartEndField($a, $b, 'date');
        });
        // Build new array node to load
        $node_array_to_return = [];
        foreach ($node_array_to_build as $ar => $nd_rt) {
          $node_array_to_return[]= $nd_rt['nid'];
        }
        $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($node_array_to_return);
        if ($nodes) {
          $data_reference_to_twig = ['start' => $request_params['search_start_month'],'end' => $request_params['search_end_month']];
          $list = $this->buildCurentEventsUpcoming($nodes, $request_params['is_reel'], $request_params['is_periode'], $data_reference_to_twig);
        }
      }
    }
    // Build return array variable
    $prev_data = [
      'month' => $request_params['prev_month'],
      'name' => ($request_params['prev_month_name']) ? t($request_params['prev_month_name']) : NULL,
      'year' => ($request_params['month'] == 1 ) ? $request_params['year'] - 1 : $request_params['year'],
    ];

    $next_data = [
      'month' => $request_params['next_month'],
      'name' => ($request_params['next_month_name'] ) ? t($request_params['next_month_name']) : NULL,
      'year' => ($request_params['month'] == 12 ) ? $request_params['year'] + 1 : $request_params['year'],
    ];

    $current_data = [
      'month' => $request_params['month'],
      'name' => $request_params['current_month_name'],
      'year' => $request_params['year'],
    ];

    $pager_data = [
      'page' => $q_page,
      'num_items' => $q_items_per_page,
      'total' => $q_total_items,
    ];

    if (is_int($find_regions_tid) && $find_regions_tid > 0) {
      $term = Term::load($find_regions_tid);
      $term_name  = $term->hasTranslation($language) ? $term->getTranslation($language)->label() : $term->label();
      $info_filter_regions = ['tid' => $find_regions_tid, 'label' => $term_name, 'path' => $regions];
    }

    // Get path module
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('gm_agenda')->getPath();

    return [
      '#theme' => 'gm_agenda_by_month',
      '#node_list' => $list,
      '#prev_data' => $prev_data,
      '#next_data' => $next_data,
      '#current_data' => $current_data,
      '#pager_data' => $pager_data,
      '#filter_regions' => ($find_regions_tid > 0) ? $info_filter_regions : NULL,
      '#all_filter_regions' => $this->getFlitersRegions('regions', ($find_regions_tid > 0) ? NULL : TRUE, $language),
      '#event_today' => [],
      '#is_today' => $request_params['is_periode'] ? $request_params['is_periode']  : FALSE,
      '#current_langcode' => $language,
      '#path_module' => $module_path,
      '#attached' => [
        'library' => [
          'gm_agenda/gm-agenda-design',
        ],
      ],
    ];
  }

  /**
   * Utility: buildCurrentDataParameters()
   * 
   * @param null $month
   *  The month reference
   * @param null $year
   *  The year reference
   * @return array
   *  All dat on request date
   */
  public function buildCurrentDataParameters($month, $year) {
    $parameters = [];
    $search_end_month_more_days = NULL;
    $route_name = \Drupal::routeMatch()->getRouteName();
    $d_day = \Drupal::service('date.formatter')->format(time(), 'custom', 'd');
    $d_month = \Drupal::service('date.formatter')->format(time(), 'custom', 'm');
    $d_year = \Drupal::service('date.formatter')->format(time(), 'custom', 'Y');
    if ($route_name == 'gm_agenda.agenda_today.en' || $route_name == 'gm_agenda.agenda_today.fr' || $route_name == 'gm_agenda.agenda') {
      $parameters = [
        'is_today' => $route_name == 'gm_agenda.agenda' ? FALSE : TRUE,
        'day' => $d_day,
        'month' => $d_month,
        'year' => $d_year,
        'is_periode' => $route_name == 'gm_agenda.agenda' ? 'upcoming' : 'today',
        'is_reel' => 'present',
      ];
    }else{
      if (!is_int((int)$month) || !is_int((int)$year) || (int)$month <= 0 || (int)$month >= 13 || (int)$year <= 2000 || (int)$year >= 2030) {
        $parameters = [
          'is_today' => FALSE,
          'day' => $d_day,
          'month' => $d_month,
          'year' => $d_year,
          'is_periode' => 'error',
          'is_reel' => 'error',
        ];
      }else{
        $parameters = [
          'is_today' => FALSE,
          'today_day' => $d_day,
          'today_month' => $d_month,
          'today_year' => $d_year,
          'day' => NULL,
          'month' => $month,
          'year' => $year,
          'is_periode' => 'between',
        ];
        if ($d_month == $month && (int)$d_year == (int)$year ) {
          $parameters += ['is_reel' => 'present'];
        }elseif ($d_month < $month && (int)$d_year == (int)$year || $d_month < $month && (int)$d_year < (int)$year || (int)$d_year < (int)$year  ) {
          $parameters += ['is_reel' => 'futur'];
        }else{
          $parameters += ['is_reel' => 'past'];
        }
        // format month year
        $d_day = NULL;
        $d_month = $month;
        $d_year = $year;
      }
    }
    $event_prev_month = $this->getNextPrevMonth($d_month, 'prev');
    $event_next_month = $this->getNextPrevMonth($d_month, 'next');
    // FInd prev next month
    $event_prev_month_name = date("F", mktime(0, 0, 0, $event_prev_month, 10));
    $event_next_month_name = date("F", mktime(0, 0, 0, $event_next_month, 10));
    $event_current_month_name = date("F", mktime(0, 0, 0, $d_month, 10));
    // Search end month
    $search_start_month = $d_year.'-'.sprintf("%02d", $d_month).'-01T00:01:00';
    $search_end_month = $d_year.'-'.sprintf("%02d", $d_month).'-'.$this->daysInMonth($d_month, $d_year).'T23:59:00';
    
    // Dispatch
    if ($route_name == 'gm_agenda.agenda_today.en' || $route_name == 'gm_agenda.agenda_today.fr') {

      $search_start_day = $d_year.'-'.sprintf("%02d", $d_month).'-'.sprintf("%02d", $d_day).'T00:00:01';
      $search_end_day = $d_year.'-'.sprintf("%02d", $d_month).'-'.sprintf("%02d", $d_day).'T23:59:59';
      $search_short_day = $d_year.'-'.sprintf("%02d", $d_month).'-'.sprintf("%02d", $d_day);
      $search_end_month_more_days = [
        'start' => $search_start_day,
        'end' =>$search_end_day,
        'short' => $search_short_day,
      ];

    }elseif($route_name == 'gm_agenda.agenda') {
      $date = new DrupalDateTime('now', 'T');
      $search_end_month_more_days = $date->format('Y-m-dTH:m:s');

    }elseif($route_name == 'gm_agenda.agenda_by_month') {
      $date = new DrupalDateTime($search_end_month, 'T');
      $date->modify('+12 hour');
      $search_end_month_more_days = $date->format('Y-m-dTH:m:s');
    }

    $date_now = new DrupalDateTime('now', 'T');

    $parameters +=[
      'prev_month' => $event_prev_month,
      'prev_month_name' => $event_prev_month_name,
      'current_month' => $d_month,
      'current_month_name' => $event_current_month_name,
      'next_month' => $event_next_month,
      'next_month_name' => $event_next_month_name,
      'search_start_month' => $search_start_month,
      'search_end_month' => $search_end_month,
      'search_end_month_more_days' => $search_end_month_more_days,
      'now' => $date_now->format('Y-m-d'),
    ];
    return $parameters;
  }

  /**
   * Utility: Build a requestion of events
   * @param string $search_end_month_more_days
   *  The date limit date end to search || date of search day
   * @param string $search_start_month
   *  The limit date start to search
   * @param string $search_end_month
   *  The limit start date end to search
   * @param string $find_regions_tid
   *  The regions path filter
   * @param string $find_tags_tid
   *  The tags path filter
   * @param string $language
   *  The current langcode
   * @param string $periode
   *  The time lap to search
   * @return mixed
   *  The data
   */
  public function buildCountRequest($search_end_month_more_days = NULL, $search_start_month = NULL, $search_end_month = NULL, $find_regions_tid = NULL, $find_tags_tid = NULL, $language = 'fr', $periode = 'today', $is_reel = 'present') {

    if (!$search_end_month_more_days && !$search_start_month && !$search_end_month && !$language) {
      return FALSE;
    }
    $query_select = \Drupal::database()->select('node_field_data', 'n');
    $query_select->leftjoin('node__field_date', 'dt', 'dt.entity_id = n.nid');

    if (is_int($find_regions_tid)) {
      $query_select->leftjoin('node__field_regions', 'rg', 'rg.entity_id = n.nid');
    }
    if (is_int($find_tags_tid)) {
      $query_select->leftjoin('node__field_tags', 'tg', 'tg.entity_id = n.nid');
    }
    $query_select->fields('n', ['nid']);
    $query_select->fields('dt', ['field_date_value', 'field_date_end_value']);

    // $query_select->groupBy('n.nid');
    $query_select->condition('n.status', 1);
    $query_select->condition('n.type', 'evenement');
    $query_select->condition('n.langcode', $language);
    if (is_int($find_regions_tid)) {
      $query_select->condition('rg.field_regions_target_id', [$find_regions_tid], 'IN');
    }
    if (is_int($find_tags_tid)) {
      $query_select->condition('tg.field_tags_target_id', [$find_tags_tid], 'IN');
    }

    // $condition_or_count = $query_select_count->orConditionGroup();

    if ($periode == 'between') {
      // $condition_or = $query_select->orConditionGroup();
      $condition_or_past = $query_select->andConditionGroup()
          ->condition('dt.field_date_value', $search_end_month, '<=')
          ->condition('dt.field_date_end_value', $search_start_month, '>=');
        $query_select->condition($condition_or_past);      
    }
    if ($periode == 'upcoming') {
      $condition_or = $query_select->orConditionGroup();
      $condition_or->condition('dt.field_date_value', $search_end_month_more_days, '>=');
      $condition_or->condition('dt.field_date_end_value', $search_end_month_more_days, '>=');
      $query_select->condition($condition_or);
    }
    if ($periode == 'today') {
      if (is_array($search_end_month_more_days)) {
        $date = new DrupalDateTime($search_end_month_more_days['start'], 'UTC');
        $date->format('Y-m-d');
        $condition_or_1 = $query_select->orConditionGroup();
        $condition_or_2 = $query_select->orConditionGroup();

        $condition_or_1 = $query_select->orConditionGroup()
          ->condition('dt.field_date_value', array($search_end_month_more_days['start'], $search_end_month_more_days['end']), 'BETWEEN')
          ->condition('dt.field_date_end_value', array($search_end_month_more_days['start'], $search_end_month_more_days['end']), 'BETWEEN')
          ->condition('dt.field_date_value', $search_end_month_more_days['end'], '<=');
          // ->condition('dt.field_date_end_value', $search_end_month_more_days['start'], '>=');
        $condition_or_1 = $query_select->andConditionGroup()
          ->condition('dt.field_date_end_value', $search_end_month_more_days['start'], '>=')
          ->condition('dt.field_date_value', $search_end_month_more_days['end'], '<=');
          // ->condition('dt.field_date_value', array($search_end_month_more_days['start'], $search_end_month_more_days['end']), 'BETWEEN');

        $condition_or_2->condition($condition_or_1);
        $query_select->condition($condition_or_2);
      }
    }
    // Fletch result
    return $query_select->countQuery()->execute()->fetchField();

  }

  /**
   * Utility: Build a requestion of events
   * @param string $search_end_month_more_days
   *  The date limit date end to search || date of search day
   * @param string $search_start_month
   *  The limit date start to search
   * @param string $search_end_month
   *  The limit start date end to search
   * @param string $find_regions_tid
   *  The regions path filter
   * @param string $find_tags_tid
   *  The tags path filter
   * @param string $language
   *  The current langcode
   * @param int $q_page
   *  The current page showing
   * @param int $q_items_per_page
   *  The number of items to show
   * @return mixed
   *  The data
   */
  public function buildDataRequest($search_end_month_more_days = NULL, $search_start_month = NULL, $search_end_month = NULL, $find_regions_tid = NULL, $find_tags_tid = NULL, $language = 'fr', $q_page = 0, $q_items_per_page = 5, $periode = 'today', $is_reel = 'present') {

    if (!$search_end_month_more_days && !$search_start_month && !$search_end_month && !$language) {
      return FALSE;
    }

    $query_select = \ Drupal::database()->select('node_field_data', 'n');
    $query_select->leftjoin('node__field_date', 'dt', 'dt.entity_id = n.nid');
    // filter
    if (is_int($find_regions_tid)) {
      $query_select->leftjoin('node__field_regions', 'rg', 'rg.entity_id = n.nid');
    }
    if (is_int($find_tags_tid)) {
      $query_select_count->leftjoin('node__field_tags', 'tg', 'tg.entity_id = n.nid');
    }

    $query_select->fields('n', ['nid']);
    $query_select->fields('dt', ['field_date_value', 'field_date_end_value']);

    // $query_select->groupBy('n.nid');
    $query_select->condition('n.status', 1);
    $query_select->condition('n.type', 'evenement');
    $query_select->condition('n.langcode', $language);
    $query_select->range(($q_page === '0' ? 0 : ((int)$q_page * (int)$q_items_per_page)), (int)$q_items_per_page);
    if (is_int($find_regions_tid)) {
      $query_select->condition('rg.field_regions_target_id', [$find_regions_tid], 'IN');
    }
    if (is_int($find_tags_tid)) {
      $query_select->condition('tg.field_tags_target_id', [$find_tags_tid], 'IN');
    }
    if ($periode == 'between') {
      // $condition_or = $query_select->orConditionGroup();
      $condition_or_past = $query_select->andConditionGroup()
          ->condition('dt.field_date_value', $search_end_month, '<=')
          ->condition('dt.field_date_end_value', $search_start_month, '>=');
        $query_select->condition($condition_or_past);      
    }
    if ($periode == 'upcoming') {
      $condition_or = $query_select->orConditionGroup();
      $condition_or->condition('dt.field_date_value', $search_end_month_more_days, '>=');
      $condition_or->condition('dt.field_date_end_value', $search_end_month_more_days, '>=');
      $query_select->condition($condition_or);
    }
    if ($periode == 'today') {
      if (is_array($search_end_month_more_days)) {
        $date = new DrupalDateTime($search_end_month_more_days['start'], 'UTC');
        $date->format('Y-m-d');
        $condition_or_1 = $query_select->orConditionGroup();
        $condition_or_2 = $query_select->orConditionGroup();

        $condition_or_1 = $query_select->orConditionGroup()
          ->condition('dt.field_date_value', array($search_end_month_more_days['start'], $search_end_month_more_days['end']), 'BETWEEN')
          ->condition('dt.field_date_end_value', array($search_end_month_more_days['start'], $search_end_month_more_days['end']), 'BETWEEN')
          ->condition('dt.field_date_value', $search_end_month_more_days['end'], '<=');
          // ->condition('dt.field_date_end_value', $search_end_month_more_days['start'], '>=');
        $condition_or_1 = $query_select->andConditionGroup()
          ->condition('dt.field_date_end_value', $search_end_month_more_days['start'], '>=')
          ->condition('dt.field_date_value', $search_end_month_more_days['end'], '<=');
          // ->condition('dt.field_date_value', array($search_end_month_more_days['start'], $search_end_month_more_days['end']), 'BETWEEN');

        $condition_or_2->condition($condition_or_1);
        $query_select->condition($condition_or_2);
      }
    }
    // Fletch result
    return $query_select->execute()->fetchAll();

  }

  /**
   * Utility: Find day in a Month.
   * @param null $month
   *  The month reference
   * @param null $year
   *  The year reference
   * @return mixed
   *  The numer day in a particular month
   */
  public function daysInMonth($month, $year) {
    // calculate number of days in a month
    return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
  }
  /**
   * Utility: Find prev and next Month
   * @param mixed $current_month
   *  The current month reference
   * @param string $type
   *  Before or after search
   * @return mixed
   *  the month formated m
   */
  public function getNextPrevMonth($current_month, $type = 'prev') {
    if (!$current_month) {
      return NULL;
    }
    if ($type == 'prev') {
      return ($current_month == 1) ? 12 : sprintf("%02d", $current_month - 1);
    }elseif($type == 'next'){
      return ($current_month == 12) ? sprintf("%02d", 1) : sprintf("%02d", $current_month + 1);
    }else {
      return NULL;
    }
  }

  /**
   * ------------------- OLD
   * Utility: find term by name and vid.
   * @param mixed $nodes
   *  each node to put in the list
   * @return mixed
   *  Array of node
   */
  public function buildCurentEvents($nodes, $is_reel = 'present', $is_periode = 'agenda') {
    $list = [];
    $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
    $view_mode = 'teaser2';
    $lp = 0;
    $lpel = 0;
    $Layout = 0;
    $date_to_compare = NULL;
    $date_day_end = NULL;
    $add_class_m = NULL;
    foreach ($nodes as $node) {
      $date_order = [];
      // Get stat date for groupin by day
      $field_date = ($node->hasField('field_date') && !$node->get('field_date')->isEmpty()) ? $node->get('field_date')->getValue()[0] : NULL;
      
      // Start loop grouping by day
      if ($field_date) {
        $object_date = $this->buildObjectDate($field_date);
        // if ($add_class_start_month) {
        //   // Add some class to design event
        //   $add_class_full = explode('T', $add_class_start_month);
        //   $add_class_m = explode('-', $add_class_full[0]);
        //   $add_class_full_end = explode('T', $add_class_end_month);
        //   $add_class_m = $add_class_m[1] == $object_date['start']['month'] || $object_date['start']['full'] >= $add_class_full[0] ? 'start' : ($object_date['end']['full'] > $add_class_full_end[0] ? 'long-end': 'end');
        // }
        
        if ($lp == 0) {
          $date_to_compare = ($object_date['start']['full'] <= $object_date['start']['now'] || $object_date['start']['full'] == $object_date['start']['now']) ? $object_date['start']['full'] : $object_date['end']['full'];
          // $list[$Layout]['is_class'] = $add_class_m;
          $list[$Layout]['is_reel'] = $is_reel;
          $list[$Layout]['date'] = $object_date;
          $list[$Layout]['depth'] = '0';
          $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
          $lpel++;
        }else {
          
          // $formatted_date = $object_date['start']['full'] <= $object_date['start']['now'] ? $object_date['start']['full'] : $object_date['end']['full'];
          $formatted_date = ($object_date['start']['full'] <= $object_date['start']['now'] || $object_date['start']['full'] == $object_date['start']['now']) ? $object_date['start']['full'] : $object_date['end']['full'];
          
          // If the date is the same
          if ($formatted_date == $date_to_compare) {
            $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
            $lpel++;
          }else{
            $Layout++;
            $lpel = 0;
            $list[$Layout]['date'] = $object_date;
            $list[$Layout]['depth'] = '0';
            $list[$Layout]['is_reel'] = $is_reel;
            // $list[$Layout]['is_class'] = $add_class_m;
            $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
            $lpel++;
            // Restart compared date
            $date_to_compare = $formatted_date;
          }
        }
        $lp++;
      }
    }
    return $list;
  }

  /**
   * Utility: find term by name and vid.
   * @param mixed $nodes
   *  each node to put in the list
   * @return mixed
   *  Array of node
   */
  public function buildCurentEventsUpcoming($nodes, $is_reel = 'present', $is_periode = 'upcoming', $search_start_month = []) {
    $list = [];
    $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
    $view_mode_default = 'teaser';
    $lp = 0;
    $lpel = 0;
    $Layout = 0;
    $class_to_add = [];
    $date_to_compare = NULL;
    $date_day_end = NULL;
    $add_class_m = NULL;
    foreach ($nodes as $node) {
      $date_order = [];
      // Get stat date for groupin by day
      $field_date = ($node->hasField('field_date') && !$node->get('field_date')->isEmpty()) ? $node->get('field_date')->getValue()[0] : NULL;
      
      // Start loop grouping by day
      if ($field_date) {
        $object_date = $this->buildObjectDate($field_date);

        $date_month_start = new DrupalDateTime($search_start_month['start'], 'T');
        $date_month_end = new DrupalDateTime($search_start_month['end'], 'T');
 
        if ($lp == 0) {
          $date_to_compare = $this->getDateToCompare($is_periode, $object_date, $date_month_start);
          $class_element_event = $this->getClassOfEvent($is_periode, $object_date, $date_month_start, $date_month_end);
          $view_mode = (!empty($class_element_event) && $class_element_event == 'start') ? $view_mode_default : ($class_element_event == 'end' ? 'teaser2' : 'teaser3');
          $list[$Layout]['is_class'][$lpel] = $class_element_event;
          $list[$Layout]['is_start_reference'] = $date_month_start->format('Y-m-d');
          $list[$Layout]['is_end_reference'] = $date_month_end->format('Y-m-d');
          $list[$Layout]['is_reel'] = $is_reel;
          $list[$Layout]['date'] = $object_date;
          $list[$Layout]['depth'] = '0';
          $list[$Layout]['is_periode'] = $is_periode;
          $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
          $lpel++;
        }else {
          // get date to compare
          $formatted_date = $this->getDateToCompare($is_periode, $object_date, $date_month_start);
          // Get class of event
          $class_element_event = $this->getClassOfEvent($is_periode, $object_date, $date_month_start, $date_month_end);
          $view_mode = (!empty($class_element_event) && $class_element_event == 'start') ? $view_mode_default : ($class_element_event == 'end' ? 'teaser2' : 'teaser3');
          // If the date is the same
          if ($formatted_date == $date_to_compare) {
            $list[$Layout]['is_class'][$lpel] = $class_element_event;
            $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
            $lpel++;
          }else{
            $Layout++;
            $lpel = 0;
            $list[$Layout]['is_class'][$lpel] = $class_element_event;
            $list[$Layout]['is_start_reference'] = $date_month_start->format('Y-m-d');
            $list[$Layout]['is_end_reference'] = $date_month_end->format('Y-m-d');
            $list[$Layout]['is_periode'] = $is_periode;
            $list[$Layout]['date'] = $object_date;
            $list[$Layout]['depth'] = '0';
            $list[$Layout]['is_reel'] = $is_reel;
            $list[$Layout]['nodes'][$lpel] = $node_view_builder->view($node, $view_mode);
            $lpel++;
            // Restart compared date
            $date_to_compare = $formatted_date;
          }
        }
        $lp++;
      }
    }
    return $list;
  }

  /**
   * Utility: find term by name and vid.
   * @param string $is_periode
   *  The type periode today|between|upcoming
   * @param array $object_date
   *  The array data date
   * @param mixed $date_month_start
   *  The object date
   * @return string
   *  The date
   */
  public function getDateToCompare($is_periode, $object_date, $date_month_start) {
    $date_to_compare = NULL;
    if ($is_periode == 'today') {
      $date_to_compare = ($object_date['start']['full'] != $object_date['start']['now']) ? $object_date['end']['full'] : $object_date['start']['full'];
    }elseif($is_periode == 'upcoming') {
      $date_to_compare = ($object_date['start']['full'] < $object_date['start']['now']) ? $object_date['end']['full'] : $object_date['start']['full'];
    }elseif($is_periode == 'between') {
      $date_to_compare = ($object_date['start']['month'] == $date_month_start->format('m') && $object_date['start']['original'] >= $date_month_start) ? $object_date['start']['full'] : $object_date['end']['full'];
    }
    return $date_to_compare;
  }

  public function getClassOfEvent($is_periode = 'today', $object_date, $date_month_start, $date_month_end) {
    $data_return = NULL;
    $date_start = $object_date['start']['full'];
    $date_now = $object_date['start']['now'];
    $date_end = $object_date['end']['full'];
    $date_month = $object_date['start']['month'];
    // $date_reference = $date_month_start->format('Y-m-d');
    $date_original = $object_date['start']['original'];
    $class_end = $date_end > $date_month_end->format('Y-m-d') ? 'long-end' : 'end';
    if ($is_periode == 'today') {
      $data_return = ($date_start != $date_now) ? $class_end : 'start';
    }elseif($is_periode == 'upcoming') {
      $data_return = ($date_start < $date_now) ? $class_end : 'start';
    }elseif($is_periode == 'between') {
      $data_return = ($date_month == $date_month_start->format('m') && $date_original >= $date_month_start->format('Y-m-d')) ? 'start' : $class_end;
    }   
    return $data_return;
  }

  public function buildObjectDate($field_date) {
    $r_date_start = [];
    $r_date_end = [];
    $now = \Drupal::service('date.formatter')->format(time(), 'custom', 'Y-m-d', date_default_timezone_get());
    $now_month = \Drupal::service('date.formatter')->format(time(), 'custom', 'm', date_default_timezone_get());
    if ($field_date) {
      if (array_key_exists('end_value', $field_date)) {
        $explode_date_hour = explode('T', $field_date['end_value']);
        $explode_date = explode('-', $explode_date_hour[0]);
        $manipulate_date_end = new DrupalDateTime($field_date['end_value'], 'T');
        $r_date_end = [
          'full' => $explode_date_hour[0],
          'time' => $explode_date_hour[1],
          'year' => $explode_date[0],
          'month' => $explode_date[1],
          'day' => $explode_date[2],
          'day_letter' => \Drupal::service('date.formatter')->format($manipulate_date_end->getTimestamp(), 'custom', 'l', date_default_timezone_get()),
          'month_letter' => \Drupal::service('date.formatter')->format($manipulate_date_end->getTimestamp(), 'custom', 'M', date_default_timezone_get()),
          'month_letter_full' => \Drupal::service('date.formatter')->format($manipulate_date_end->getTimestamp(), 'custom', 'F', date_default_timezone_get()),
          'timestamp' => $manipulate_date_end->getTimestamp(),
          'original' => $field_date['end_value'],
          // 'now' => $now,
        ];
      }
      if (array_key_exists('value', $field_date)) {
        $explode_date_hour = explode('T', $field_date['value']);
        $explode_date = explode('-', $explode_date_hour[0]);
        $manipulate_date_start = new DrupalDateTime($field_date['value'], 'T');
        $r_date_start = [
          'full' => $explode_date_hour[0],
          'time' => $explode_date_hour[1],
          'year' => $explode_date[0],
          'month' => $explode_date[1],
          'day' => $explode_date[2],
          'day_letter' => \Drupal::service('date.formatter')->format($manipulate_date_start->getTimestamp(), 'custom', 'l', date_default_timezone_get()),
          'month_letter' => \Drupal::service('date.formatter')->format($manipulate_date_start->getTimestamp(), 'custom', 'M', date_default_timezone_get()),
          'month_letter_full' => \Drupal::service('date.formatter')->format($manipulate_date_start->getTimestamp(), 'custom', 'F', date_default_timezone_get()),
          'timestamp' => $manipulate_date_start->getTimestamp(),
          'original' => $field_date['value'],
          'now' => $now,
          'now_month' => $now_month
        ];
      }
    }
    return [
      'start' => $r_date_start,
      'end' => $r_date_end,
    ];
  }

  public function getTitle($month, $year) {

    if (!is_int((int)$month) || !is_int((int)$year) || (int)$year <= 2000 || (int)$year >= 2030) {
      return t("Events");
    }
    
    if ((int)$month <= 0 || (int)$month >= 13) {
      return t("Events");
    }

    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $formatted_date = $year.'-'.$month.'-01T08:00:00';
    $date = new DrupalDateTime($formatted_date, 'UTC');
    if (!$date) {
      return t("Events");
    }
    $current_month = \Drupal::service('date.formatter')->format($date->getTimestamp(), 'custom', 'F', date_default_timezone_get());
    return ($language == 'en') ? ucfirst($current_month).  ' events': 'Évènements du mois de ' . ucfirst($current_month);
  }

  public function getTitleToday() {
    // $is_today = FALSE;
    // if (!is_string($month) || $month != 'today' || $month != 'aujourd-hui') {
    //   return 'Aujourd\'hui';
    // }
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $current_month = \Drupal::service('date.formatter')->format(time(), 'custom', 'l d F Y', date_default_timezone_get());
    return ($language == 'en') ? ucfirst($current_month).  ' events': 'Évènements du ' . ucfirst($current_month);
  }

  /**
   * Sorts a string array item by an arbitrary key.
   *
   * @param array $a
   *   First item for comparison.
   * @param array $b
   *   Second item for comparison.
   * @param string $key
   *   The key to use in the comparison.
   *
   * @return int
   *   The comparison result for uasort().
   */
  public function sortByDateStartEndField($a, $b, $key) {
    $datetime1 = strtotime($a[$key]);
    $datetime2 = strtotime($b[$key]);
    return $datetime1 - $datetime2;
  }

  /**
   * Utility: find term by name and vid.
   * @param null $alias
   *  Term vid
   * @return int
   *  Term id or 0 if none.
   */
  protected function getTidByName($alias = NULL) {
    $tid = 0;
    if ($alias && is_string($alias)) {
      $query_select = \Drupal::database()->select('path_alias', 'p');
      $query_select->fields('p', ['alias', 'path']);
      $query_select->condition('p.status', 1);
      $query_select->condition('p.alias', $alias);
      $result_select = $query_select->execute()->fetchAssoc();
      if (is_array($result_select)) {
        $get_tid = explode('/', $result_select['path']);
        if (isset($get_tid[3])) {
          $tid = (int)$get_tid[3];
        }
      }
      return $tid;
    }
  }

  /**
   * Utility: find term by name and vid.
   * @param null $vid
   *  The vid of vocabulary
   * @param null $with_count
   *  Show count node term  used
   * @param string $language
   *  Current langcode
   * @return array
   *  Array of menu term
   */
  protected function getFlitersRegions($vid = NULL, $with_count = NULL, $language = 'fr') {
    
    $menu_regions = NULL;
    if ($vid && is_string($vid)) {
      $query = \Drupal::entityQuery('taxonomy_term');
      $query->condition('vid', $vid);
      $tids = $query->execute();
      if ($tids) {
        $terms = Term::loadMultiple($tids);
        $clean_string = \Drupal::service('pathauto.alias_cleaner');
        foreach ($terms as $term) {
          $count_node =  NULL;
          if ($with_count && is_bool($with_count)) {
            $query = \Drupal::database()->select('taxonomy_index', 'ti');
            $query->addField('ti', 'tid', 'tid');
            $query->addExpression("count(*)", 'count');
            $query->condition('ti.tid', $term->id());
            $query->groupBy('ti.tid');
            $query->orderBy('count', 'DESC');
            $query->range(0, 50);
            $count_node = $query->execute()->fetchAssoc();
          }
          $term_name  = $term->hasTranslation($language) ? $term->getTranslation($language)->label() : $term->label();
          $menu_regions[] = [
            'label' => $term_name,
            'tid' => $term->id(),
            'path' => $clean_string->cleanString($term_name),
            'count' => ($count_node && is_array($count_node)) ? $count_node['count'] : 0
          ];
        }
      }
    }
    return $menu_regions;
  }
}