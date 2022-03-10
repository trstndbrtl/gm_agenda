<?php

namespace Drupal\gm_agenda\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides a 'DonateBlock' block.
 *
 * @Block(
 *  id = "up_coming_show_block",
 *  admin_label = @Translation("UpComing Show block"),
 * )
 */
class UpComingShowBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $gm_upcoming_items_number = isset($config['gm_upcoming_items_number']) ? $config['gm_upcoming_items_number'] : 3;

    $form['gm_upcoming_items_number'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Nombre d\'élément'),
      '#description' => $this->t('Combien d\'élément doit lister la vue ?.'),
      '#default_value' => $gm_upcoming_items_number,
    ];

    $gm_upcoming_display_mode = isset($config['gm_upcoming_display_mode']) ? $config['gm_upcoming_display_mode'] : 'teaser';
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

    $gm_upcoming_design_direction = isset($config['gm_upcoming_design_direction']) ? $config['gm_upcoming_design_direction'] : 'column';
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

    $tag_filter = NULL;
    $gm_upcoming_filter_tags = isset($config['gm_upcoming_filter_tags']) ? $config['gm_upcoming_filter_tags'] : NULL;
    if ($gm_upcoming_filter_tags) {
      $tag_filter = Term::load($gm_upcoming_filter_tags);
    }
    $form['gm_upcoming_filter_tags'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#title' => 'Tags',
      '#selection_settings' => [
        'target_bundles' => ['tags'],
      ],
      '#description' => $this->t('Fiter by tags'),
      // '#required' => TRUE,
      '#default_value' => $tag_filter,
    ];

    $regions_filter = NULL;
    $gm_upcoming_filter_regions = isset($config['gm_upcoming_filter_regions']) ? $config['gm_upcoming_filter_regions'] : NULL;
    if ($gm_upcoming_filter_regions) {
      $regions_filter = Term::load($gm_upcoming_filter_regions);
    }
    $form['gm_upcoming_filter_regions'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#title' => 'Régions',
      '#selection_settings' => [
        'target_bundles' => ['regions'],
      ],
      '#description' => $this->t('Fiter by regions'),
      // '#required' => TRUE,
      '#default_value' => $regions_filter,
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
    $this->configuration['gm_upcoming_filter_tags'] = $values['gm_upcoming_filter_tags'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Get the number of element to display
    $config = $this->getConfiguration();
    $up_count_list = isset($config['gm_upcoming_items_number']) ? (int)$config['gm_upcoming_items_number'] : 3;
    $up_display_mode = isset($config['gm_upcoming_display_mode']) ? (string)$config['gm_upcoming_display_mode'] : 'teaser';
    $up_design_direction = isset($config['gm_upcoming_design_direction']) ? (string)$config['gm_upcoming_design_direction'] : 'teaser';
    $gm_upcoming_filter_tags = isset($config['gm_upcoming_filter_tags']) ? (string)$config['gm_upcoming_filter_tags'] : NULL;
    // Format element for Date request
    $timezone = date_default_timezone_get();
    $now = new \DateTime('now', new \DateTimeZone($timezone));
    $date_format = $now->format('Y-m-d');

    // Prepare the current language
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    // Request all event 
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


    $entity_type_manager = \Drupal::entityTypeManager();
    $node_view_builder = $entity_type_manager->getViewBuilder('node');

    $nodes = $entity_type_manager->getStorage('node')->loadMultiple($nids);

    $list = ['nodes' => []];
    foreach ($nodes as $node) {
      $list['nodes'][$node->id()] = $node_view_builder->view($node, $up_display_mode);
    }


    return [
      '#theme' => 'gm_upcoming_block',
      '#node_list' => $list,
      '#direction' => $up_design_direction,
      '#number_items' => $up_count_list,
    ];
  }

}
