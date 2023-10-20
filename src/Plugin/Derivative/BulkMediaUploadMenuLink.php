<?php

namespace Drupal\media_upload\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generate links for media bundles - for bulk upload enabled.
 *
 * @package Drupal\media_upload\Plugin\Derivative
 */
class BulkMediaUploadMenuLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The base plugin ID.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * BulkMediaUploadMenuLink constructor.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    $base_plugin_id,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    $mediaTypes = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    /** @var \Drupal\media\MediaTypeInterface $mediaType */
    foreach ($mediaTypes as $mediaType) {
      $mediaUploadSettings = $mediaType->getThirdPartySettings('media_upload');
      if (empty($mediaUploadSettings) || !isset($mediaUploadSettings['enabled']) || FALSE === (bool) $mediaUploadSettings['enabled']) {
        // Skip types where the bulk upload is disabled or not set.
        continue;
      }

      $typeId = $mediaType->id();
      $linkId = "media_upload.bulk_media_upload:$typeId";
      $links[$linkId] = $base_plugin_definition;
      $links[$linkId]['title'] = $mediaType->label();
      $links[$linkId]['description'] = $mediaType->getDescription();
      $links[$linkId]['route_name'] = 'media_upload.bulk_media_upload';
      $links[$linkId]['route_parameters']['type'] = $typeId;
      $links[$linkId]['requirements']['_permission'] = 'upload media';
    }

    return $links;
  }

}
