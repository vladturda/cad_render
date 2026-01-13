<?php

namespace Drupal\cad_render\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileUsage\FileUsageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;

#[Block(
  id: 'cad_render_block',
  admin_label: new TranslatableMarkup('CAD Render block'),
  category: new TranslatableMarkup('CAD Render')
)]

/**
 * Provides a block for rendering a CAD model.
 */
class CADRenderBlock extends BlockBase {
    
  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $cad_fid = $config['cad_render_file'] ?? [];
  
    if ($cad_fid) {
      $cad_render_file = File::load(reset($cad_fid));
      $cad_render_file_path = $cad_render_file->createFileUrl();
      $config['cad_render_file'] = $cad_render_file_path;
    }

    $unique_id = $this->getPluginId() . ':' . spl_object_id($this);

    return [
      '#theme' => 'cad_render_block',
      '#attributes' => [
        'class' => ['cad-render-block-wrapper'],
        'data-unique-id' => $unique_id,
      ],
      '#attached' => [
        'library' => ['cad_render/base'],
        'drupalSettings' => [
          'cadRender' => [
            'blocks' => [
              $unique_id => $config,
            ],
          ],
        ],
      ],
    ];
  }
  
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'cad_render_file' => [],
      'animation' => 'none',
      'camera_type' => 'perspective',
      'camera_zoom' => 1,
      'width' => '',
      'height' => '',
      'background_color' => '',
      'material' => 'default',
      'solid_color' => '#ffffff',
      'transparency' => FALSE,
      'opacity' => 1.0,
      'wireframe' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['cad_render_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CAD File'),
      '#description' => $this->t('Provide a CAD model to display in this block.'),
      '#upload_location' => 'public://cad_render_files/',
      '#upload_validators' => [
        'FileExtension' => ['glb'],
      ],
      '#default_value' => $this->configuration['cad_render_file'] ?? [],
    ];


    $form['animation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Animation'),
      '#options' => [
        'none' => $this->t('None'),
        'rotate' => $this->t('Rotate'),
        'rotate_on_hover' => $this->t('Rotate on Hover'),
      ],
      '#default_value' => $this->configuration['animation'] ?? 'none',
    ];


    $form['camera'] = [
      '#type' => 'details',
      '#title' => $this->t('Camera'),
      '#open' => FALSE,
    ];
    $form['camera']['camera_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Camera Type'),
      '#options' => [
        'perspective' => $this->t('Perspective'),
        'orthographic' => $this->t('Orthographic'),
      ],
      '#default_value' => $this->configuration['camera_type'] ?? 'perspective',
    ];    
    $form['camera']['camera_zoom'] = [
      '#type' => 'number',
      '#title' => $this->t('Zoom'),
      '#description' => $this->t('Camera zoom, default value is 1.'),
      '#min' => 0,
      '#step' => 0.01,
      '#default_value' => $this->configuration['camera_zoom'] ?? 1.0,
    ];


    $form['dimensions'] = [
      '#type' => 'details',
      '#title' => $this->t('Dimensions'),
      '#open' => FALSE,
    ];
    $form['dimensions']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Leave blank to use container width.'),
      '#default_value' => $this->configuration['width'] ?? '',
    ];
    $form['dimensions']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Leave blank to use container height.'),
      '#default_value' => $this->configuration['height'] ?? '',
    ];


    $form['render_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Render Options'),
      '#open' => FALSE,
    ];
    $form['render_options']['background_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background Color'),
      '#description' => $this->t('Provide hex color code like 0xffffff. Leave blank for transparent background.'),
      '#default_value' => $this->configuration['background_color'] ?? '',
      '#placeholder' => '0xffffff',
    ];
    $form['render_options']['material'] = [
      '#type' => 'radios',
      '#title' => $this->t('Material'),
      '#options' => [
        'default' => $this->t('Default'),
        'solid_color' => $this->t('Solid Color'),
      ],
      '#default_value' => $this->configuration['material'] ?? 'default',
    ];
    $form['render_options']['solid_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Solid Color'),
      '#default_value' => $this->configuration['solid_color'] ?? '#ffffff',
    ];
    $form['render_options']['transparency'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Transparency'),
      '#default_value' => $this->configuration['transparency'] ?? FALSE,
    ];
    $form['render_options']['opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Opacity'),
      '#description' => $this->t('Set the opacity level (0 to 1).'),
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.1,
      '#default_value' => $this->configuration['opacity'] ?? 1,
    ];
    $form['render_options']['wireframe'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Wireframe'),
      '#default_value' => $this->configuration['wireframe'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $new_fids = $form_state->getValue('cad_render_file') ?? [];

    // Save new cad_render_file.
    foreach ($new_fids as $fid) {
      if ($file = File::load($fid)) {
        $file->setPermanent();
        $file->save();
      }
    }

    $this->configuration['cad_render_file'] = $new_fids;
    $this->configuration['animation'] = $form_state->getValue('animation');
    $this->configuration['camera_type'] = $form_state->getValue(['camera', 'camera_type']);
    $this->configuration['camera_zoom'] = $form_state->getValue(['camera', 'camera_zoom']);
    $this->configuration['width'] = $form_state->getValue(['dimensions', 'width']);
    $this->configuration['height'] = $form_state->getValue(['dimensions', 'height']);
    $this->configuration['background_color'] = $form_state->getValue(['render_options', 'background_color']);
    $this->configuration['material'] = $form_state->getValue(['render_options', 'material']);
    $this->configuration['solid_color'] = $form_state->getValue(['render_options', 'solid_color']);
    $this->configuration['transparency'] = $form_state->getValue(['render_options', 'transparency']);
    $this->configuration['opacity'] = $form_state->getValue(['render_options', 'opacity']);
    $this->configuration['wireframe'] = $form_state->getValue(['render_options', 'wireframe']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
  }
}
