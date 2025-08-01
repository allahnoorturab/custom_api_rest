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

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private AccountProxyInterface $currentUser;

  /**
   * Constructs a new DashboardResource.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The formats supported by this resource.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   */
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

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to GET requests.
   *
   * Returns a dashboard overview for the current user.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the dashboard data.
   */
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


  /**
   * Retrieves a list of published articles.
   *
   * Queries the 'article' content type for published nodes, sorted by creation date
   * in descending order, and limited by the specified amount.
   *
   * @param int $limit
   *   The maximum number of articles to retrieve. Defaults to 3.
   *
   * @return array
   *   An array of articles, each containing:
   *   - 'title': The article title.
   *   - 'date': The article creation date in 'Y-m-d' format.
   */

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

  /**
   * Retrieves a list of upcoming events.
   *
   * Queries the 'event' content type for published nodes, sorted by event date
   * in ascending order, and limited by the specified amount.
   *
   * @param int $limit
   *   The maximum number of events to retrieve. Defaults to 2.
   *
   * @return array
   *   An array of events, each containing:
   *   - 'title': The event title.
   *   - 'date': The event date in 'Y-m-d' format.
   */
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
