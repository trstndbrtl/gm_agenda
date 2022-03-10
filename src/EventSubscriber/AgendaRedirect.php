<?php
 
namespace Drupal\gm_agenda\EventSubscriber;
 
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
 
class AgendaRedirect implements EventSubscriberInterface {
 
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => [
        ['redirectionToday', 29],
      ]
    ];
  }
 
  /**
   * Redirection des contenus de type media vers la page du media dans l'app.
   */
  public function redirectionToday(GetResponseEvent $event) {
    // get object request
    $request = $event->getRequest();
    // Get parametter
    $now_route = $request->get('_route');
    $current_path = $request->getRequestUri();
    // Get current language
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    // ksm($now_route = $request->attributes);
    if ($now_route === 'gm_agenda.agenda_today.en' && $language == 'fr' && strpos($current_path, 'today') !== false) {
      $url = Url::fromRoute('gm_agenda.agenda_today.fr');
      $new_response = new RedirectResponse($url->toString(), '302');
      $event->setResponse($new_response);
    }elseif($now_route === 'gm_agenda.agenda_today.fr' && $language == 'en' && strpos($current_path, 'aujourd-hui') !== false) {
      $url = Url::fromRoute('gm_agenda.agenda_today.en');
      $new_response = new RedirectResponse($url->toString(), '302');
      $event->setResponse($new_response);
    }
    else {
      return;
    }
  }
}