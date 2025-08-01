<?php
namespace Drupal\custom_api_rest\Plugin\rest\resource;

use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
/**
 * Provides a Dashboard REST Resource
 *
 * @RestResource(
 *   id = "dashboard_rest_resource",
 *   label = @Translation("Dashboard API Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/dashboard"
 *   }
 * )
 */
class DashboardResource extends ResourceBase {

  protected $currentUser;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self{
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user')
    );
  }

  public function get() {
    $uid = $this->currentUser->id();
    $user = User::load($uid);

    $response = [
      'user' => [
        'name' => $user->getDisplayName(),
        'photo' => $user->user_picture->entity?->createFileUrl() ?? null,
      ],
      'news' => $this->getArticles(3),
      'events' => $this->getEvents(2),
      'notifications' => 2, // Replace with real logic
      'membership' => [
        'status' => 'Active',
        'expires' => '2025-12-31',
      ],
    ];

    return new ResourceResponse($response);
  }

  private function getArticles($limit = 3): array {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->accessCheck()
      ->execute();

    $nodes = Node::loadMultiple($nids);
    $data = [];

    foreach ($nodes as $node) {
      $data[] = [
        'title' => $node->getTitle(),
        'date' => date('Y-m-d', $node->getCreatedTime()),
      ];
    }
    return $data;
  }

  private function getEvents($limit = 2): array {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'event')
      ->condition('status', 1)
      ->sort('field_event_date', 'ASC')
      ->range(0, $limit)
      ->accessCheck()
      ->execute();

    $nodes = Node::loadMultiple($nids);
    $data = [];

    foreach ($nodes as $node) {
      $data[] = [
        'title' => $node->getTitle(),
        'date' => $node->get('field_event_date')->value ?? null,
      ];
    }
    return $data;
  }
}
