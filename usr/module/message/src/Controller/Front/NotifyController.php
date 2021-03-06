<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt BSD 3-Clause License
 */

namespace Module\Message\Controller\Front;

use Module\Message\Service;
use Pi\Mvc\Controller\ActionController;
use Pi\Paginator\Paginator;
use Pi;

/**
 * System notification controller
 *
 * Feature list:
 *
 *  - List of notifications
 *  - Show details of a notification
 *  - Mark the notifications as read
 *  - Delete one or more notifications
 *
 * @author Xingyu Ji <xingyu@eefocus.com>
 */
class NotifyController extends ActionController
{
    /**
     * Render new message count of tab navigation
     *
     * @return void
     */
    protected function renderNav()
    {
        //current user id
        $userId = Pi::user()->getUser()->id;

        $messageTitle = sprintf(
            __('Private message(%s unread)'),
            Service::getUnread($userId, 'message')
        );
        $notificationTitle = sprintf(
            __('Notification(%s unread)'),
            Service::getUnread($userId, 'notification')
        );
        $this->view()->assign('messageTitle', $messageTitle);
        $this->view()->assign('notificationTitle', $notificationTitle);
    }

    /**
     * List notifications
     *
     * @return void
     */
    public function indexAction()
    {
        $page = _get('p', 'int');
        $page = $page ?: 1;
        $limit = Pi::config('list_number');
        $offset = (int) ($page - 1) * $limit;

        //current user id
        $userId = Pi::user()->getUser()->id;

        // dismiss alert
        Pi::user()->message->dismissAlert($userId);

        $model = $this->getModel('notification');
        //get notification list count
        /*
        $select = $model->select()
                        ->columns(array(
                            'count' => new \Zend\Db\Sql\Predicate\Expression(
                                'count(*)'
                            )
                        ))
                        ->where(array('uid' => $userId, 'is_deleted' => 0));
        $count = $model->selectWith($select)->current()->count;
        */
        $count = $model->count(array('uid' => $userId, 'is_deleted' => 0));
        if ($count) {
            //get notification list
            $select = $model->select()
                            ->where(array(
                                'uid' => $userId,
                                'is_deleted' => 0
                            ))
                            ->order('time_send DESC')
                            ->limit($limit)
                            ->offset($offset);
            $rowset = $model->selectWith($select);
            $notificationList = $rowset->toArray();
            //jump to last page
            if (empty($notificationList) && $page > 1) {
                $this->redirect()->toRoute('', array(
                    'controller' => 'notify',
                    'action'     => 'index',
                    'p'          => ceil($count / $limit),
                ));

                return;
            }

            array_walk($notificationList, function (&$v, $k) {
                //markup content
                $v['content'] = Pi::service('markup')->compile(
                    $v['content'],
                    'text',
                    array('nl2br' => false)
                );
            });

            $paginator = Paginator::factory(intval($count), array(
                'page'          => $page,
                'limit'         => $limit,
                'url_options'   => array(
                    'page_param' => 'p',
                    'params'        => array(
                        'module'        => $this->getModule(),
                        'controller'    => 'notify',
                        'action'        => 'index',
                    ),
                ),
            ));
            $this->view()->assign('paginator', $paginator);
        } else {
            $notificationList = array();
        }
        $this->renderNav();
        $this->view()->assign('notifications', $notificationList);

        return;
    }

    /**
     * Notification detail
     *
     * @return void
     */
    public function detailAction()
    {
        $notificationId = _get('mid', 'int');
        $notificationId = $notificationId ?: 0;
        //current user id
        $userId = Pi::user()->getUser()->id;

        // dismiss alert
        Pi::user()->message->dismissAlert($userId);

        $model = $this->getModel('notification');
        //get notification
        $select = $model->select()
                        ->where(array(
                            'id' => $notificationId,
                            'uid' => $userId
                        ));
        $rowset = $model->selectWith($select)->current();
        if (!$rowset) {
            return;
        }
        $detail = $rowset->toArray();

        //markup content
        $detail['content'] = Pi::service('markup')->render($detail['content']);

        if (!$detail['is_read']) {
            //mark the notification as read
            $model->update(array('is_read' => 1),
                           array('id' => $notificationId));
        }

        $this->view()->assign('notification', $detail);
        $this->renderNav();

        return;
    }

    /**
     * Mark the notification as read
     *
     * @return void
     */
    public function markAction()
    {
        $notificationIds = _get('ids',
                                'regexp',
                                array('regexp' => '/^[0-9,]+$/'));
        $page = _get('p', 'int');
        $page = $page ?: 1;
        //current user id
        $userId = Pi::user()->getUser()->id;
        if (empty($notificationIds)) {
            $this->redirect()->toRoute('', array(
                'controller' => 'notify',
                'action'     => 'index',
                'p'          => $page
            ));
        }

        if (strpos($notificationIds, ',')) {
            $notificationIds = explode(',', $notificationIds);
        }

        $model = $this->getModel('notification');
        $model->update(array('is_read' => 1), array(
            'id'  => $notificationIds,
            'uid' => $userId
        ));

        $this->redirect()->toRoute('', array(
            'controller' => 'notify',
            'action'     => 'index',
            'p'          => $page
        ));
    }

    /**
     * Delete notifications
     *
     * @return void
     */
    public function deleteAction()
    {
        $notificationIds = _get('ids',
                                'regexp',
                                array('regexp' => '/^[0-9,]+$/'));
        $page = _get('p', 'int');
        $page = $page ?: 1;

        if (strpos($notificationIds, ',')) {
            $notificationIds = explode(',', $notificationIds);
        }
        if (empty($notificationIds)) {
            $this->redirect()->toRoute('', array(
                'controller' => 'notify',
                'action'     => 'index',
                'p'          => $page
            ));
        }
        $userId = Pi::user()->getUser()->id;
        $model = $this->getModel('notification');
        $model->update(array('is_deleted' => 1), array(
            'id'  => $notificationIds,
            'uid' => $userId
        ));

        $this->redirect()->toRoute('', array(
            'controller' => 'notify',
            'action'     => 'index',
            'p'          => $page
        ));

        return;
    }
}
