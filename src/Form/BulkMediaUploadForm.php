<?php

namespace Drupal\media_upload\Form;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BulkMediaUploadForm.
 *
 * @package Drupal\media_upload\Form
 */
class BulkMediaUploadForm extends FormBase {

  const COMPLETE_FILE_NAME = 0;
  const FILE_NAME = 1;
  const EXT_NAME = 2;
  const FILENAME_REGEX = '/(^[\w\-\. ]+)\.([a-zA-Z0-9]+)/';

  /**
   * Default max file size.
   *
   * @var string
   */
  protected $defaultMaxFileSize = '32MB';

  /**
   * Media type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaTypeStorage;

  /**
   * Media entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Logger for the media_upload module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('logger.factory'),
      $container->get('token')
    );
  }

  /**
   * BulkMediaUploadForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger for the media_upload module.
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    LoggerChannelFactoryInterface $logger,
    Token $token
  ) {
    $this->mediaTypeStorage = $entityTypeManager->getStorage('media_type');
    $this->mediaStorage = $entityTypeManager->getStorage('media');
    $this->entityFieldManager = $entityFieldManager;
    $this->logger = $logger->get('media_upload');
    $this->token = $token;
    $this->defaultMaxFileSize = \format_size(\file_upload_max_size())->render();
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'media_upload_bulk_upload_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\media\MediaTypeInterface $type
   *   The media type.
   *
   * @return array
   *   The form structure.
   *
   * @throws \InvalidArgumentException
   */
  public function buildForm(array $form, FormStateInterface $form_state, MediaTypeInterface $type = NULL) {
    if (NULL === $type) {
      drupal_set_message('Invalid media type.', 'warning');
      return [];
    }

    $mediaUploadSettings = $type->getThirdPartySettings('media_upload');
    if (empty($mediaUploadSettings) || !isset($mediaUploadSettings['enabled']) || FALSE === (bool) $mediaUploadSettings['enabled']) {
      drupal_set_message(t('Bulk-upload is not enabled for the @typeName type.', [
        '@typeName' => $type->label(),
      ]), 'warning');
      return [];
    }

    $typeId = $type->id();
    $targetFieldSettings = $this->getTargetFieldSettings($typeId);

    $extensions = $targetFieldSettings['file_extensions'];
    $maxFileSize = empty($targetFieldSettings['max_filesize']) ? $this->defaultMaxFileSize : $targetFieldSettings['max_filesize'];

    $form['#tree'] = TRUE;
    $form['information_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'media-bulk-upload-information-wrapper',
        ],
      ],
    ];
    $form['information_wrapper']['information_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => $this->t('Information'),
      '#attributes' => [
        'class' => [
          'form-control-label',
        ],
        'for' => 'media_bulk_upload_information',
      ],
    ];

    $information = '<p>' . $this->t('Allowed extensions: @allowedExtensions', [
      '@allowedExtensions' => \str_replace(' ', ', ', \trim($extensions)),
    ]) . '</p>';
    $information .= '<p>' . $this->t('Maximum file size for each file: @maxFileSize', [
      '@maxFileSize' => $maxFileSize,
    ]) . '</p>';

    $form['information_wrapper']['information'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#id' => 'media_bulk_upload_information',
      '#name' => 'media_bulk_upload_information',
      '#value' => $information,
    ];

    $form['dropzonejs'] = [
      '#type' => 'dropzonejs',
      '#title' => $this->t('Dropzone'),
      '#required' => TRUE,
      '#dropzone_description' => $this->t('Click or drop your files here'),
      '#max_filesize' => $maxFileSize,
      '#extensions' => $extensions,
    ];

    $form['media_type'] = [
      '#type' => 'value',
      '#value' => $typeId,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $errorFlag = FALSE;
      $fileCount = 0;
      $createdMedia = [];
      $values = $form_state->getValues();
      if (empty($values['dropzonejs']) || empty($values['dropzonejs']['uploaded_files'])) {
        $this->logger->warning('No documents were uploaded');
        drupal_set_message($this->t('No documents were uploaded'), 'warning');
        return;
      }

      /** @var array $files */
      $files = $values['dropzonejs']['uploaded_files'];

      $typeId = $values['media_type'];
      $targetFieldSettings = $this->getTargetFieldSettings($typeId);
      // Prepare destination. Patterned on protected method
      // FileItem::doGetUploadLocation and public method
      // FileItem::generateSampleValue.
      $fileDirectory = \trim($targetFieldSettings['file_directory'], '/');
      // Replace tokens. As the tokens might contain HTML we convert
      // it to plain text.
      $fileDirectory = PlainTextOutput::renderFromHtml($this->token
        ->replace($fileDirectory));
      $targetDirectory = $targetFieldSettings['uri_scheme'] . '://' . $fileDirectory;
      \file_prepare_directory($targetDirectory, FILE_CREATE_DIRECTORY);

      /** @var array $file */
      foreach ($files as $file) {
        $fileInfo = [];
        if (\preg_match(self::FILENAME_REGEX, $file['filename'], $fileInfo) !== 1) {
          $errorFlag = TRUE;
          $this->logger->warning('@filename - Incorrect file name', ['@filename' => $file['filename']]);
          drupal_set_message($this->t('@filename - Incorrect file name', ['@filename' => $file['filename']]), 'warning');
          continue;
        }

        // Use \in_array for opcode optimization.
        // @see: https://github.com/Roave/FunctionFQNReplacer
        if (!\in_array(
          $fileInfo[self::EXT_NAME],
          explode(' ', $targetFieldSettings['file_extensions']),
          FALSE
        )) {
          $errorFlag = TRUE;
          $this->logger->error('@filename - File extension is not allowed', ['@filename' => $file['filename']]);
          drupal_set_message($this->t('@filename - File extension is not allowed', ['@filename' => $file['filename']]), 'error');
          continue;
        }

        $destination = $targetDirectory . '/' . $file['filename'];
        $data = \file_get_contents($file['path']);
        $fileEntity = \file_save_data($data, $destination);

        if (FALSE === $fileEntity) {
          $errorFlag = TRUE;
          $this->logger->warning('@filename - File could not be saved.', [
            '@filename' => $file['filename'],
          ]);
          drupal_set_message('@filename - File could not be saved.', [
            '@filename' => $file['filename'],
          ], 'warning');
          continue;
        }

        $media = $this->mediaStorage->create($this->getNewMediaValues($typeId, $fileInfo, $fileEntity));
        $media->save();
        $createdMedia[] = $media;
        $fileCount++;
      }

      $form_state->set('created_media', $createdMedia);
      if ($errorFlag && !$fileCount) {
        $this->logger->warning('No documents were uploaded');
        drupal_set_message($this->t('No documents were uploaded'), 'warning');
        return;
      }

      if ($errorFlag) {
        $this->logger->info('Some documents have not been uploaded');
        drupal_set_message($this->t('Some documents have not been uploaded'), 'warning');
        $this->logger->info('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]);
        drupal_set_message($this->t('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]));
        return;
      }

      $this->logger->info('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]);
      drupal_set_message($this->t('@fileCount documents have been uploaded', ['@fileCount' => $fileCount]));
      return;
    }
    catch (\Exception $e) {
      $this->logger->critical($e->getMessage());
      drupal_set_message($e->getMessage(), 'error');

      return;
    }
  }

  /**
   * Return the media type target field.
   *
   * @param string $typeId
   *   Type ID.
   *
   * @return string
   *   The name of the target field.
   *
   * @throws \InvalidArgumentException
   */
  protected function getTargetFieldName($typeId) {
    /** @var \Drupal\media\MediaTypeInterface $type */
    $type = $this->mediaTypeStorage->load($typeId);

    if (NULL === $type) {
      throw new \InvalidArgumentException(t('The @typeId type can not be found.', [
        '@typeId' => $typeId,
      ]));
    }

    $mediaUploadSettings = $type->getThirdPartySettings('media_upload');
    if (empty($mediaUploadSettings) || !isset($mediaUploadSettings['enabled']) || FALSE === (bool) $mediaUploadSettings['enabled']) {
      throw new \InvalidArgumentException(t('Bulk-upload is not enabled for the @typeName type.', [
        '@typeName' => $type->label(),
      ]));
    }

    return $mediaUploadSettings['upload_target_field'];
  }

  /**
   * Get the target field settings for the type.
   *
   * @param string $typeId
   *   Type ID.
   *
   * @return array|mixed[]
   *   The field settings.
   *
   * @throws \InvalidArgumentException
   */
  protected function getTargetFieldSettings($typeId) {
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('media', $typeId);
    $targetFieldName = $this->getTargetFieldName($typeId);
    /** @var \Drupal\field\Entity\FieldConfig $targetField */
    $targetField = $fieldDefinitions[$targetFieldName];
    return $targetField->getSettings();
  }

  /**
   * Builds the array of all necessary info for the new media entity.
   *
   * @param string $typeId
   *   Type ID.
   * @param array $fileInfo
   *   File info.
   * @param \Drupal\file\FileInterface $file
   *   File entity.
   *
   * @return array
   *   Return an array describing the new media entity.
   *
   * @throws \InvalidArgumentException
   */
  protected function getNewMediaValues(
    $typeId,
    array $fileInfo,
    FileInterface $file
  ) {
    $targetFieldName = $this->getTargetFieldName($typeId);

    return [
      'bundle' => $typeId,
      'name' => $fileInfo[self::FILE_NAME],
      $targetFieldName => [
        'target_id' => $file->id(),
        'title' => $fileInfo[self::FILE_NAME],
      ],
    ];
  }

}
