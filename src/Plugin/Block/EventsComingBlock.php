<?php

namespace Drupal\gm_agenda\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
// use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
// use Drupal\node\Entity\Node;
// use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
// use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'EventsComingBlock' block.
 *
 * @Block(
 *  id = "events_coming_block",
 *  admin_label = @Translation("Up Coming Event block"),
 * )
 */
class EventsComingBlock extends BlockBase {


  // /**
  //  * The entity type manager.
  //  *
  //  * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
  //  */
  // protected $entityDisplayRepository;

  // /**
  //  * Constructs a NodeEmbedBlock instance.
  //  *
  //  * @param array $configuration
  //  *   A configuration array containing information about the plugin instance.
  //  * @param string $plugin_id
  //  *   The plugin_id for the formatter.
  //  * @param mixed $plugin_definition
  //  *   The plugin implementation definition.
  //  * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
  //  *   The entity display repository.
  //  */
  // public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityDisplayRepositoryInterface $entity_display_repository) {
  //   parent::__construct($configuration, $plugin_id, $plugin_definition);
  //   $this->entityDisplayRepository = $entity_display_repository;
  // }

  // /**
  //  * {@inheritdoc}
  //  */
  // public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
  //   return new static(
  //     $configuration,
  //     $plugin_id,
  //     $plugin_definition,
  //     $container->get('entity_display.repository')
  //   );
  // }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $gm_upcoming_items_number = isset($config['gm_upcoming_items_number']) ? $config['gm_upcoming_items_number'] : 3;
    $gm_upcoming_display_mode =  isset($config['gm_upcoming_display_mode'])  ? $config['gm_upcoming_display_mode'] : 'teaser';
    $gm_upcoming_design_direction = isset($config['gm_upcoming_design_direction'])  ? $config['gm_upcoming_design_direction'] : 'column';

    $form['gm_upcoming_items_number'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Nombre d\'élément'),
      '#description' => $this->t('Combien d\'élément doit lister la vue ?.'),
      '#default_value' => $gm_upcoming_items_number,
    ];

    $form['gm_upcoming_display_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display mode'),
      '#options' => array(
				'tiny' => $this->t('Tiny'),
				'teaser' => $this->t('Teaser'),
        'full' => $this->t('Full'),
			),
      '#description' => $this->t('Le design de l\'agenda.'),
      '#required' => TRUE,
      '#default_value' => $gm_upcoming_display_mode,
    ];

    $form['gm_upcoming_design_direction'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display direction'),
      '#options' => array(
				'column' => $this->t('Column'),
				'row' => $this->t('Row'),
			),
      '#description' => $this->t('Column or row'),
      '#required' => TRUE,
      '#default_value' => $gm_upcoming_design_direction,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $list_count = (int)$form_state->getValue('gm_upcoming_items_number');
    if( $list_count < 1 || $list_count > 25 ){
      $form_state->setErrorByName('gm_upcoming_items_number', $this->t('Un nombre d\'élément entre 1 et 25.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['gm_upcoming_items_number'] = $values['gm_upcoming_items_number'];
    $this->configuration['gm_upcoming_display_mode'] = $values['gm_upcoming_display_mode'];
    $this->configuration['gm_upcoming_design_direction'] = $values['gm_upcoming_design_direction'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // $block['content'] = t('Content not found');
    // Get the number of element to display
    $config = $this->getConfiguration();
    $up_count_list = $config['gm_upcoming_items_number'] ? (int)$config['gm_upcoming_items_number'] : 3;
    $up_display_mode = $config['gm_upcoming_display_mode'] ? (string)$config['gm_upcoming_display_mode'] : 'teaser';
    $up_design_direction = $config['gm_upcoming_design_direction'] ? (string)$config['gm_upcoming_design_direction'] : 'teaser';
    // Format element for Date request
    $timezone = date_default_timezone_get();
    $now = new \DateTime('now', new \DateTimeZone($timezone));
    $date_format = $now->format('Y-m-d');

    // Prepare the current language
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'evenement')
      ->condition('langcode', $language)
      ->sort('field_date_debut', 'ASC')
      ->range(0, $up_count_list);

    $orGroup = $query->orConditionGroup()
      ->condition('field_date_debut', $date_format, '>=')
      ->condition('field_date_fin', $date_format, '>=');
      
    // Add the group to the query.
    $query->condition($orGroup);
    $nids = $query->execute();

    $output = [];
    // $entity_type_manager = \Drupal::entityTypeManager();
    // $node_view_builder = $entity_type_manager->getViewBuilder('node');
    $entity_type = 'node';
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
    // $nodes = $entity_type_manager->getStorage('node')->loadMultiple($nids);

    if ($nids) {

      foreach ($nids as $n => $nid) {

        $node = $storage->load($nid);
        $build = $view_builder->view($node, $up_display_mode);
        $output[] = render($build);
        // $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
        // $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        // $node = $storage->load($nid);
        // # code...
      }

      ksm($output);
      // $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
      // Or a use the static loadMultiple method on the entity class:
      // $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

      // And then you can view/build them all together:
      // $build = \Drupal::entityTypeManager()->getViewBuilder('node')->viewMultiple($nodes, 'teaser');

      // ksm($build);


      // $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
      // $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      // $node = $storage->load($n);

    }

    $block['#revue'] = [
      '#theme' => 'item_list_upcoming_wall',
      '#list_type' => 'ul',
      '#items' => $output,
      '#attributes' => ['class' => 'uk-grid-small uk-flex-inline', 'uk-grid' => ''],
      '#wrapper_attributes' => ['class' => 'uk-container uk-container-small'],
    ];


    // return [
    //   '#theme' => 'gm_upcoming_block',
    //   '#node_list' => $list,
    //   '#direction' => $up_design_direction,
    //   '#number_items' => $up_count_list,
    // ];

    return $block;
  }

}
