<?php

namespace Drupal\gm_agenda\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Controller class for requests from the smart buttons.
 */
class AgendaController extends ControllerBase {

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

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'evenement')
      ->condition('langcode', $language)
      ->sort('field_date_debut', 'ASC');

    $orGroup = $query->orConditionGroup()
      ->condition('field_date_debut', $date_format, '>=')
      ->condition('field_date_fin', $date_format, '>=');
      
    // Add the group to the query.
    $query->condition($orGroup);
    $nids = $query->execute();


    // $entity_type_manager = \Drupal::entityTypeManager();
    $node_view_builder = $this->entityTypeManager->getViewBuilder('node');
    $view_mode = 'teaser';

    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $list = ['nodes' => []];
    foreach ($nodes as $node) {
      $list['nodes'][$node->id()] = $node_view_builder->view($node, $view_mode);
    }

    return [
      '#theme' => 'gm_agenda_list',
      '#node_list' => $list,
      '#var2' => NULL,
    ];
  }

  /**
   * Callback requested after approving the order.
   *
   * Dispatches the 'approve' event to Drupal.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response for buttons.
   */
  public function approve(Request $request) {
    $content = $request->getContent();
    if (!empty($content)) {
      $data = json_decode($content, TRUE);
      // ksm($data);
      $element = [];
      return new JsonResponse('ok');
    }
    else {
      return new JsonResponse(NULL, Response::HTTP_NOT_ACCEPTABLE);
    }
  }

}
