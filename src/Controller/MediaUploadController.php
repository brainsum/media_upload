<?php

namespace Drupal\media_upload\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MediaUploadController.
 *
 * @package Drupal\media_upload\Controller
 */
class MediaUploadController extends ControllerBase {

  /**
   * Menu link tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.link_tree')
    );
  }

  /**
   * MediaUploadController constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   Menu link tree.
   */
  public function __construct(MenuLinkTreeInterface $menuTree) {
    $this->menuTree = $menuTree;
  }

  /**
   * Build bulk upload link list.
   *
   * Note, we render according to menu links.
   *
   * @see \Drupal\media_upload\Plugin\Derivative\BulkMediaUploadMenuLink
   *
   * @return array
   *   Render array.
   */
  public function getListContent() {
    if ($content = $this->buildListContent('bulk_media_upload')) {
      return [
        '#theme' => 'entity_add_list',
        '#bundles' => $content,
        '#add_type_message' => $this->t('You do not have any bulk upload forms.'),
      ];
    }

    return [
      '#markup' => $this->t('You do not have any bulk upload forms.'),
    ];

  }

  /**
   * Provide a single block on the administration overview page.
   *
   * @param string $menuName
   *   The menu item to be displayed.
   *
   * @return array
   *   An array of menu items, as expected by entity-add-list.html.twig.
   */
  protected function buildListContent($menuName) {
    $content = [];
    // Only find the children of this link.
    $parameters = new MenuTreeParameters();
    $tree = $this->menuTree->load($menuName, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    foreach ($tree as $key => $element) {
      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        // @todo Bubble cacheability metadata of both accessible and
        //   inaccessible links. Currently made impossible by the way admin
        //   blocks are rendered.
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      $content[$key]['label'] = $link->getTitle();
      $content[$key]['description'] = $link->getDescription();
      $content[$key]['add_link'] = Link::fromTextAndUrl($link->getTitle(), $link->getUrlObject());
    }
    \ksort($content);
    return $content;
  }

}
