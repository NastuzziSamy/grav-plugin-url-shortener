<?php
namespace Grav\Plugin;

use Grav\Common\{
    Plugin, Page\Page, Page\Pages, Utils
};
use RocketTheme\Toolbox\Event\Event;

/**
 * Class UrlShortenerPlugin
 * @package Grav\Plugin
 */
class UrlShortenerPlugin extends Plugin
{
    /**
     * 64 caracters based on https://tools.ietf.org/html/rfc3986#section-2.3.
     *
     * @var string
     */
    protected const CARACTERS = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_";

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onPageNotFound' => ['onPageNotFound', 100],
            'onPageInitialized' => ['onPageInitialized', 100]
        ]);
    }

    public function getShortenerLength() {
        return $this->config->get('plugins.url-shortener.length');
    }

    public function getFromMd5Length() {
        return floor($this->config->get('plugins.url-shortener.length') * 1.5);
    }

    public function getPathId(Page $page): string
    {
        return substr($page->id(), -$this->getFromMd5Length());
    }

    public function encode(string $pathId): string
    {
        $shortenerId = base64_encode(hex2bin($pathId));
        $shortenerId = str_replace('+', '-', $shortenerId);
        $shortenerId = str_replace('/', '_', $shortenerId);

        return substr($shortenerId, 0, $this->getShortenerLength());
    }

    public function decode(string $shortenerId): string
    {
        $shortenerId = str_replace('-', '+', $shortenerId);
        $shortenerId = str_replace('_', '/', $shortenerId);
        $pathId = bin2hex(base64_decode($shortenerId));

        return substr($pathId, 0, $this->getFromMd5Length());
    }

    public function buildUrl(Page $page): string
    {
        $root = $this->grav['uri']->rootUrl(true);
        $uri = $this->config->get('plugins.url-shortener.uri');
        $shortenerId = $this->encode($this->getPathId($page));

        return trim($root.'/'.$uri.'/'.$shortenerId, '/');
    }

    /**
     * Do some work for this event, full details of events can be found
     * on the learn site: http://learn.getgrav.org/plugins/event-hooks
     *
     * @param Event $event
     */
    public function onPageInitialized(Event $event)
    {
        $page = $event['page'];

        if (($page->header()->shorten_url ?? true) !== false) {
            $page->modifyHeader('external_url', $this->buildUrl($page));
        }
    }

    /**
     * Do some work for this event, full details of events can be found
     * on the learn site: http://learn.getgrav.org/plugins/event-hooks
     *
     * @param Event $e
     */
    public function onPageNotFound(Event $event)
    {
        // Get a variable from the plugin configuration
        $currentUri = $this->grav['uri']->path();
        $uri = $this->config->get('plugins.url-shortener.uri');
        $length = $this->getShortenerLength();

        if (preg_match('#^/'.$uri.'/(['.self::CARACTERS.']{'.$length.'})/?$#', $currentUri, $matches)) {
            $pathId = $this->decode(end($matches));
            $pages = new Pages($this->grav);
            $pages->init();

            foreach ($pages->all() as $page) {
                if (Utils::endsWith($page->id(), $pathId)) {
                    $event->stopPropagation();

                    $this->grav->redirect($page->route(), 302);
                }
            }

            if ($this->config->get('plugins.url-shortener.home_if_wrong')) {
                $this->grav->redirect($this->grav['uri']->rootUrl(true), 301);
            }
        }
    }
}
