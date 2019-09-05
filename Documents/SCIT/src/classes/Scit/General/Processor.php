<?php

namespace Scit\General;

class Processor extends \Scit\General\Templates\General{

    public function getUserManager(){
        return $this->getUtils()->init('Users-Manager');
    }

    private function setContinueUrl(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator',[
            '__settings' => [
                'retain' => false
            ]
        ]);

        $fullRequestUrl = $request->getFullRequestUrl();
        if($validator->isUrlFromOrigin($fullRequestUrl)){
            $session = $this->getSession();
            $result = $this->getUtils()->init('General-Validator')->filter([
                'url' => $fullRequestUrl,
            ],[
                'url' => 'sanitize_string,urlencode'
            ]);

            $session->set('page-->continueUrl',$result['url']);
        }
        return $this;
    }

    public function setPageNotification($type,$notification){
        $session = $this->getSession();
        $session->set('page-->notification',[
            'type' => $type,
            'notification' => $notification
        ]);
        return $this;
    }

    public function getContinueUrlAfter($event){
        $session = $this->getSession();
        $continueUrl = $session->get('page-->continueUrl');
        if(is_string($continueUrl)){
            $session->remove('page-->continueUrl');
            return $continueUrl;
        }

        switch(true){
            case (isset(array_flip([
                'user-logout'
            ])[$event])):
                return '/';
            break;

            case (isset(array_flip([
                'user-login',
                'user-register',
                'user-account-verification',
                'user-complete-registration'
            ])[$event])):
                return '/user/dashboard';
            break;

            default:
                return '/';
            break;
        }
    }

    public function getPageNotification(){
        $session = $this->getSession();
        $pageNotification = $session->get('page-->notification');
        if(is_array($pageNotification)){
            $session->remove('page-->notification');
            return $pageNotification;
        }
        return false;
    }

    public function verifyTokenFor(string $name,string $token){
        $session = $this->getSession();
        $known = $session->get("tokens-->{$name}");
        if(is_string($known)){
            return hash_equals($known,$token);
        }
        return false;
    }

    public function getTokenFor($name){
        $session = $this->getSession();
        $token = $this->getUtils()::random(30);
        $session->set("tokens-->{$name}",$token);
        return $token;
    }

    public function process($page,$internal = false){
        if(!$internal){
            $this->reset();
        }

        $request = $this->getUtils()->init('General-Request');
        $session = $this->getSession();
        $response = $this->getUtils()->init('General-Response');
        $responder = $this->getUtils()->init('General-JsonResponder',[
            '__settings' => [
                'retain' => false
            ]
        ]);
        $htmlManager = $this->getUtils()->init('General-HtmlManager',[
            '__settings' => [
                'retain' => false
            ]
        ]);
        $userManager = $this->getUserManager();
        $isLogged = (int) $userManager->isLogged();

        $pagesThatRequireAuth = [
            'user-logout',
            'user-account-verification',
            'user-account-approval',
            'user-complete-registration',
            'user-dashboard-home',
            'internship-provider',
            'internship-providers',
            'general-administrator-dashboard-home',
            'general-administrator-view-subject',
            'general-administrator-view-subjects',
            'general-administrator-view-student',
            'general-administrator-view-students',
            'general-administrator-view-discipline',
            'general-administrator-view-disciplines',
            'general-administrator-view-profession',
            'general-administrator-view-professions',
            'general-administrator-view-temperament',
            'general-administrator-view-temperaments',
            'general-administrator-view-question',
            'general-administrator-view-questions',
            'general-administrator-view-event',
            'general-administrator-view-events',
            'general-view-faq',
            'general-view-faqs',
            'general-add-faq',
            'general-view-support-ticket',
            'general-view-support-tickets',
            'general-add-support-ticket',
            'general-view-user-portfolio',
            'general-view-stakeholder-portfolio',
            'general-add-user-portfolio',
            'general-add-stakeholder-portfolio',
            'general-edit-user-portfolio',
            'general-edit-stakeholder-portfolio',
            'general-administrator-add-subject',
            'general-administrator-add-profession',
            'general-administrator-add-discipline',
            'general-administrator-add-temperament',
            'general-administrator-add-question',
            'general-administrator-add-event',
            'general-administrator-view-approval-requests',
            'general-administrator-messenger',
            'support-administrator-dashboard-home',
            'internship-provider-administrator-dashboard-home',
            'internship-provider-administrator-view-students',
            'internship-provider-administrator-view-applicants',
            'secondary-school-administrator-dashboard-home',
            'secondary-school-administrator-view-students',
            'secondary-school-administrator-view-applicants',
            'institution-administrator-dashboard-home',
            'student-dashboard-home',
            'student-take-test',
            'handle-chat-request',
            'general-administrator-view-general-administrators',
            'general-administrator-view-support-administrators',
            'general-administrator-view-internship-provider-administrators',
            'general-administrator-view-institution-administrators',
            'general-administrator-view-secondary-school-administrators',
            'general-administrator-view-students',
            'general-administrator-view-internship-providers',
            'general-administrator-view-institutions',
            'general-administrator-view-secondary-schools',
            'get-data'
        ];

        $pagesThatRejectsAuth = [
            'home',
            'user-login',
            'user-register',
            'user-forgot-password'
        ];

        $normalPages = [
            'update-data',
            'get-regions',
            'get-lgas',
            'get-secondary-schools',
            'get-support-ticket-categories',
            'about-us',
            'services',
            'faqs',
            '404',
            'contact-us',
            'blog',
            'restricted-access',
            'event',
            'events',
            'discipline',
            'disciplines',
            'institution',
            'institutions',
        ];

        $sectionData = [];
        $addData = \Closure::bind(function($location,$data) use(&$sectionData){
            $pointer = &$sectionData;
            $this->getUtils()::setDataInArray($pointer,$location,$data);
            return true;
        },$this);

        if($request->isPost()){
            $token = $request->get('token') ?: '';
            if(!$this->verifyTokenFor($page,$token)){
                 /*Redirect to same page if invalid token is presented and show an error*/
                $this->setPageNotification('error','Invalid request originating from this client');
                return $response->redirect($request->getFullRequestUrl());
            }
            $addData('data-->post',$request->get());
        }

        $addData('data-->user-->isLogged',$isLogged);
        $getData = \Closure::bind(function() use(&$sectionData,&$addData,&$isLogged,&$page){
            $pageNotification = $this->getPageNotification();
            if(is_array($pageNotification)){
                $addData('data-->pageNotification',$pageNotification);
            }

            $errorResult = $this->get('errorResult',true);
            if(is_array($errorResult)){
                $addData('data-->errors',$errorResult);
            }

            if($page !== '404'){
                if($isLogged){
                    $addData('data-->user',array_replace_recursive($this->getUtils()::getDataFromArray($sectionData,'data-->user'),$this->getSession()->get('userData')));
                    $addData('data-->user-->logout',[
                        'url' => '/user/logout',
                        'token' => $this->getTokenFor('user-logout')
                    ]);
                }
            }

            return $sectionData;
        },$this);

        /*Handling normal pages*/
        if(isset(array_flip($normalPages)[$page])){
            if($internal || $request->isGet()){

                switch($page){
                    case 'faqs':
                        $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $addData('data-->header-->title','Scit Faqs');

                        $from = ((int) $request->getQueryDataFor('from'));
                        $limit = 50;
                        $requestData = [
                            'from' => $from,
                            'limit' => $limit,
                            'filters' => [
                                [
                                    'pointer' => 'faqId',
                                    'value' => 0,
                                    'compareUsing' => 'greater than'
                                ]
                            ]
                        ];

                        $faqs = $adminManager->get('faqs-list',$requestData);
                        $totalFaqs = $adminManager->get('faqs-count',$requestData);
                        $addData('data-->faqs-->list',$faqs);
                        $addData('data-->faqs-->total-->count',$totalFaqs);
                        $addData('data-->faqs-->from',$from);
                        $addData('data-->faqs-->increment',$limit);
                        return $response->write($htmlManager->render('faq.html',$getData()));
                    break;

                    case 'about-us':
                        return $response->write($htmlManager->render('aboutus.html',$getData()));
                    break;

                    case 'blog':
                        return $response->write($htmlManager->render('blog.html',$getData()));
                    break;

                    case 'services':

                    break;

                    case 'restricted-access':
                        if($internal){
                            return $response->write($htmlManager->render('restricted.html',$getData()));
                        }else{
                            return $response->redirect('/');
                        }
                    break;

                    case '404':
                        $addData('data-->header-->title','Page Not Found');
                        $addData('data-->request-->url',$request->getFullRequestUrl());

                        return $response->write($htmlManager->render('404.html',$getData()));
                    break;

                    case 'contact-us':

                    break;

                    case 'get-regions':
                        if(!$this->verifyTokenFor($page,$request->get('token'))){
                            return $responder(false,'Oooops an error occurred');
                        }

                        $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $requestData = [
                            'filters' => [
                                [
                                    'pointer' => 'countryId',
                                    'value' => $this->getUtils()->getDataOfHash($request->get('countryId')),
                                    'compareUsing' => 'equality'
                                ]
                            ],
                            'limit' => 10000,
                            'from' => ((int) $request->get('from') ?: 0)
                        ];

                        $result = $adminManager->get('regions-list',$requestData);

                        if(\is_array($result)){
                            return $responder(true,$result);
                        }

                        return $responder(true,[]);
                    break;

                    case 'get-lgas':
                        if(!$this->verifyTokenFor($page,$request->get('token'))){
                            return $responder(false,'Oooops an error occurred');
                        }

                        $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $requestData = [
                            'filters' => [
                                [
                                    'pointer' => 'regionId',
                                    'value' => $this->getUtils()->getDataOfHash($request->get('regionId')),
                                    'compareUsing' => 'equality'
                                ]
                            ],
                            'limit' => 10000,
                            'from' => ((int) $request->get('from') ?: 0)
                        ];

                        $countryId = $request->get('countryId');
                        if($countryId){
                            $requestData['filters'][] = [
                                'pointer' => 'countryId',
                                'value' => $this->getUtils()->getDataOfHash($countryId),
                                'compareUsing' => 'equality'
                            ];
                        }

                        $result = $adminManager->get('local-government-areas-list',$requestData);

                        if(\is_array($result)){
                            return $responder(true,$result);
                        }

                        return $responder(true,[]);
                    break;

                    case 'get-secondary-schools':
                        if(!$this->verifyTokenFor($page,$request->get('token'))){
                            return $responder(false,'Oooops an error occurred');
                        }

                        $for = $request->get('for');
                        if($for !== 'register'){
                            if(!$this->getSession()->get('adminData-->general-administrator-->isApproved')){
                                return $responder('false','invalid request');
                            }
                        }

                        $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $requestData = ($for === 'register' ? [
                            'filters' => [
                                [
                                    'pointer' => 'secondarySchoolRegionId',
                                    'compareUsing' => 'equality',
                                    'value' => $this->getUtils()->getDataOfHash($request->get('regionId')) ?: 0
                                ],
                                [
                                    'pointer' => 'secondarySchoolCountryId',
                                    'compareUsing' => 'equality',
                                    'value' => $this->getUtils()->getDataOfHash($request->get('countryId')) ?: 0
                                ],
                                [
                                    'pointer' => 'secondarySchoolLgaId',
                                    'compareUsing' => 'equality',
                                    'value' => $this->getUtils()->getDataOfHash($request->get('lgaId')) ?: 0
                                ]
                            ],
                            'limit' => 10000
                        ] : [
                            'filters' => (json_decode($request->get('filters'),true) ?: []),
                            'limit' => 50,
                            'from' => ((int) $request->get('from') ?: 0)
                        ]);

                        $result = $adminManager->get('secondary-schools-list',$requestData);
                        $out = [];
                        if(\is_array($result)){
                            foreach($result as $school){
                                $out[] = [
                                    'id' => $school['stakeholderId'],
                                    'name' => $school['stakeholderName']
                                ];
                            }
                            return $responder(true,$out);
                        }
                        return $responder(true,[]);
                    break;

                    case 'get-support-ticket-categories':
                        if(!$this->verifyTokenFor($page,$request->get('token'))){
                            return $responder(false,'Oooops an error occurred');
                        }

                        $priorityId = $this->getUtils()->getDataOfHash($request->get('for'));

                        if(!$priorityId){
                            return $responder('false','invalid request');
                        }

                        $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $requestData = [
                            'filters' => [
                                [
                                    'pointer' => 'supportTicketPriorityId',
                                    'compareUsing' => 'equality',
                                    'value' => $priorityId
                                ]
                            ],
                            'limit' => 10000
                        ];

                        $result = $adminManager->get('support-ticket-categories-list',$requestData);
                        if(\is_array($result)){
                            return $responder(true,$result);
                        }
                        return $responder(true,[]);
                    break;

                    case 'event':
                        $eventId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('eventId'));
                        $eventData = $this->getUtils()->init('Users-Admin-Manager')->get('event-data',[
                            'eventId' => $eventId
                        ]);

                        $addData('data-->event',$eventData);
                        return $response->write($htmlManager->render('event.html',$getData()));
                    break;

                    case 'events':
                        $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $addData('data-->header-->title','Scit Events');

                        $from = ((int) $request->getQueryDataFor('from'));
                        $limit = 50;
                        $requestData = [
                            'from' => $from,
                            'limit' => $limit,
                            'filters' => [
                                [
                                    'pointer' => 'eventId',
                                    'value' => 0,
                                    'compareUsing' => 'greater than'
                                ]
                            ]
                        ];

                        $events = $adminManager->get('events-list',$requestData);

                        foreach($events as &$event){
                            $this->getUtils()::setDataInArray($event,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                'url' => $request->getFullRequestUrl()
                            ])->changePath('/event')->changeQuery([
                                'eventId' => $event['id']
                            ])->getUrlString());
                        }

                        $totalEvents = $adminManager->get('events-count',$requestData);
                        $addData('data-->events-->list',$events);
                        $addData('data-->events-->total-->count',$totalEvents);
                        $addData('data-->events-->from',$from);
                        $addData('data-->events-->increment',$limit);
                        return $response->write($htmlManager->render('events.html',$getData()));
                    break;

                    case 'institution':
                        $institutionId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('institutionId'));
                        $institutionData = $this->getUtils()->init('Users-Admin-Manager')->get('institution-data',[
                            'institutionId' => $institutionId
                        ]);

                        $addData('data-->institution',$institutionData);
                        return $response->write($htmlManager->render('institution.html',$getData()));
                    break;

                    case 'institutions':
                        $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $addData('data-->header-->title','Scit Institutions');

                        $from = ((int) $request->getQueryDataFor('from'));
                        $limit = 50;
                        $requestData = [
                            'from' => $from,
                            'limit' => $limit,
                            'filters' => [
                                [
                                    'pointer' => 'institutionId',
                                    'value' => 0,
                                    'compareUsing' => 'greater than'
                                ],
                                [
                                    'pointer' => 'institutionApprovalStatus',
                                    'value' => 1,
                                    'compareUsing' => 'equality'
                                ]
                            ]
                        ];

                        $institutions = $adminManager->get('institutions-list',$requestData);

                        foreach($institutions as &$institution){
                            $this->getUtils()::setDataInArray($institution,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                'url' => $request->getFullRequestUrl()
                            ])->changePath('/institution')->changeQuery([
                                'institutionId' => $institution['stakeholderId']
                            ])->getUrlString());
                        }

                        $totalInstitutions = $adminManager->get('institutions-count',$requestData);
                        $addData('data-->institutions-->list',$institutions);
                        $addData('data-->institutions-->total-->count',$totalInstitutions);
                        $addData('data-->institutions-->from',$from);
                        $addData('data-->institutions-->increment',$limit);
                        return $response->write($htmlManager->render('institutions.html',$getData()));
                    break;
                }
            }

            if($request->isPost()){
                switch($page){
                    case 'update-data':
                        $action = $request->get('__action');
                        $for = $request->get('__for');
                        $usersAdminManager = $this->getUtils()->init('Users-Admin-Manager');
                        $usersManager = $this->getUtils()->init('Users-Manager');

                        if(isset(array_flip([
                            'update',
                            'add',
                            'delete',
                            'view'
                        ])[$action])){
                            switch($for){
                                case 'testSatifactionResponse':
                                    if($userManager->hasPermissionAs('student')){
                                        $result = $usersAdminManager->processTestResponse($request->get('data'));
                                        return $responder($result);
                                    }else{
                                        return $responder(false,'Invalid request...');
                                    }
                                break;

                                case 'account-status':
                                    if($action == 'update'){
                                        if($userManager->hasPermissionAs('general-administrator') || ($userManager->hasPermissionAs('secondary-school-administrator') && ($request->get('for') === 'student'))){
                                            $result = $usersAdminManager->updateAccountStatus([
                                                'for' => $request->get('for'),
                                                'forId' => $request->get('forId'),
                                                'status' => $request->get('action')
                                            ]);

                                            if($result){
                                                $this->setPageNotification(($result['status'] === 'ok' ? 'success' : 'error'),$result['response']);
                                            }else{
                                                $this->setPageNotification('error','An error occured while updating data');
                                            }
                                        }else{
                                            $this->setPageNotification('error','An error occured');
                                        }
                                    }
                                break;

                                case 'faq':
                                    if($userManager->hasPermissionAs('general-administrator') || $userManager->hasPermissionAs('support-administrator')){
                                        switch($action){
                                            case 'add':

                                                $result = $usersAdminManager->addFaq($request->get('data'));

                                                if(is_array($result)){
                                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                                    if($status === 'error'){
                                                        $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                                    }

                                                    if($status === 'ok'){
                                                        $totalFailed = $this->getUtils()::getDataFromArray($result,'response-->totalFailed');
                                                        $totalExists = $this->getUtils()::getDataFromArray($result,'response-->totalExists');
                                                        $totalAdded = $this->getUtils()::getDataFromArray($result,'response-->totalAdded');

                                                        $getText = function($count,$text){
                                                            if($count > 1){
                                                                $text .= 's';
                                                            }
                                                            return "{$count} {$text}";
                                                        };

                                                        $pageNotification = '';
                                                        $addNotification = function($notification) use (&$pageNotification){
                                                            $out = '';
                                                            if(strlen($pageNotification)){
                                                                $out .= ', ';
                                                            }
                                                            $pageNotification = $out.$notification;
                                                            return true;
                                                        };

                                                        if($totalAdded){
                                                            $addNotification("Congratulations {$getText($totalAdded,'faq')} was added succesfully");
                                                            if($totalFailed){
                                                                $addNotification("{$getText($totalFailed,'faq')} had errors while adding");
                                                            }
                                                            if($totalExists){
                                                                $addNotification("{$getText($totalExists,'faq')} already exists");
                                                            }
                                                            $this->setPageNotification('success',$pageNotification);
                                                        }else{
                                                            if($totalFailed){
                                                                $addNotification("{$getText($totalFailed,'faq')} had errors while adding");
                                                            }
                                                            if($totalExists){
                                                                $addNotification("{$getText($totalExists,'faq')} already exists");
                                                            }
                                                            $this->setPageNotification('error',$pageNotification);
                                                        }
                                                    }
                                                }else{
                                                    $this->setPageNotification('error','Ooops... error adding faq data');
                                                }
                                            break;

                                            case 'update':
                                                $result = $usersAdminManager->updateFaq($request->get('data'));
                                                if(is_array($result)){
                                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                                    $isSuccess = false;

                                                    if($status === 'ok'){
                                                        $isSuccess = true;
                                                    }

                                                    $this->setPageNotification(($isSuccess ? 'success' : 'error'),$this->getUtils()::getDataFromArray($result,'response'));
                                                }else{
                                                    $this->setPageNotification('error','Invalid request while updating data');
                                                }
                                            break;

                                            case 'delete':
                                                $result = $usersAdminManager->removeFaq($request->get('data'));
                                                if(is_array($result)){
                                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                                    $isSuccess = false;

                                                    if($status === 'ok'){
                                                        $isSuccess = true;
                                                    }

                                                    $this->setPageNotification(($isSuccess ? 'success' : 'error'),$this->getUtils()::getDataFromArray($result,'response'));
                                                }else{
                                                    $this->setPageNotification('error','Invalid request while deleting data');
                                                }
                                            break;
                                        }
                                    }else{
                                        $this->setPageNootification('error','Invalid request');
                                    }
                                break;

                                case 'ticket':
                                    switch($action){
                                        case 'add':
                                            $result = $usersAdminManager->addTicket($request->get('data'));
                                            if($request->get('__type') === 'response'){
                                                if(is_array($result)){
                                                    return $responder($result);
                                                }else{
                                                    return $responder(false,'Invalid request...');
                                                }
                                            }else{
                                                if(is_array($result)){
                                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                                    $this->setPageNotification(($status === 'error' ?: 'success'),$this->getUtils()::getDataFromArray($result,'response'));
                                                }else{
                                                    $this->setPageNotification('error','Invalid request...');
                                                }
                                            }
                                        break;

                                        case 'update':
                                            $result = $usersAdminManager->updateTicket($request->get('data'));
                                            if(is_array($result)){
                                                return $responder($result);
                                            }else{
                                                return $responder(false,'Invalid request...');
                                            }
                                        break;

                                        case 'delete':
                                            $result = $usersAdminManager->removeTicket($request->get('data'));
                                            if(is_array($result)){
                                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                                $this->setPageNotification(($status === 'error' ?: 'success'),$this->getUtils()::getDataFromArray($result,'response'));
                                            }else{
                                                $this->setPageNotification('error','Invalid request...');
                                            }
                                        break;

                                        case 'view':
                                            $userType = $this->getSession()->get('userData-->type');
                                            $requestType = $request->get('type');

                                            switch($requestType){
                                                case 'tickets':
                                                    $filters = json_decode($request->get('filters'),true);
                                                    $orders = json_decode($request->get('orders'),true);

                                                    if(!is_array($filters)){
                                                        $filters = [];
                                                    }

                                                    if(!is_array($orders)){
                                                        $orders = [];
                                                    }

                                                    if($userType === 'general-administrator'){
                                                        $filters = array_merge([
                                                            [
                                                                'compareUsing' => 'greater than',
                                                                'pointer' => 'supportTicketId',
                                                                'value' => 0
                                                            ]
                                                        ],$filters);

                                                        $orders = array_merge($orders,[
                                                            [
                                                                'pointer' => 'supportTicketPriority',
                                                                'type' => 'desc'
                                                            ],
                                                            [
                                                                'pointer' => 'supportTicketId',
                                                                'type' => 'asc'
                                                            ]
                                                        ]);
                                                    }else{

                                                        $fromId;
                                                        if($userType === 'student'){
                                                            $fromId = $this->getSession()->get('studentData-->id');
                                                        }else{
                                                            $fromId = $this->getSession()->get("adminData-->{$userType}-->id");
                                                        }

                                                        $filters = array_merge([
                                                            [
                                                                'compareUsing' => 'equality',
                                                                'pointer' => 'supportTicketFrom',
                                                                'value' => $userType
                                                            ],
                                                            [
                                                                'compareUsing' => 'equality',
                                                                'pointer' => 'supportTicketFromId',
                                                                'value' => $fromId
                                                            ]
                                                        ],$filters);

                                                        $orders = array_merge($orders,[
                                                            [
                                                                'pointer' => 'supportTicketId',
                                                                'type' => 'asc'
                                                            ]
                                                        ]);
                                                    }

                                                    $result = $usersAdminManager->get('support-tickets-list',[
                                                        'limit' => 50,
                                                        'from' => ((int) $request->get('from') ?: 0),
                                                        'filters' => $filters,
                                                        'orders' => $orders
                                                    ]);

                                                    if(is_array($result)){
                                                        return $responder($result);
                                                    }

                                                    return $responder(false,'Invalid request');
                                                break;

                                                case 'ticket-replies':
                                                    $ticketId = $this->getUtils()->getDataOfHash($request->get('ticketId'));

                                                    if(is_numeric($ticketId)){
                                                        $result = $usersAdminManager->get('support-ticket-replies',[
                                                            'limit' => 20,
                                                            'from' => ((int) $request->get('from') ?: 0),
                                                            'ticketId' => $ticketId
                                                        ]);

                                                        if(is_array($result)){
                                                            return $responder(true,$result);
                                                        }
                                                    }

                                                    return $responder(false,'Invalid request');
                                                break;
                                            }
                                        break;
                                    }
                                break;

                                case 'portfolio':
                                    switch($action){
                                        case 'add':
                                            $for = $request->get('for');
                                            $result;

                                            switch($for){
                                                case 'user':
                                                    $result = $usersAdminManager->addUserPortfolio($request->get('data'));
                                                break;

                                                case 'stakeholder':
                                                    $result = $usersAdminManager->addStakeholderPortfolio($request->get('data'));
                                                break;
                                            }

                                            if(is_array($result)){
                                                return $responder($result);
                                            }else{
                                                return $responder(false,'Invalid request...');
                                            }

                                            if(is_array($result)){
                                                return $responder($result);
                                            }else{
                                                return $responder(false,'Invalid request...');
                                            }
                                        break;

                                        case 'update':
                                            if(!$usersManager->hasPermissionAs($this->getSession()->get('userData-->type'))){
                                                return $responser(false,'Invalid request');
                                            }

                                            $for = $request->get('for');
                                            $result;

                                            switch($for){
                                                case 'user':
                                                    $result = $usersAdminManager->updateUserPortfolio($request->get('data'));
                                                break;

                                                case 'stakeholder':
                                                    $result = $usersAdminManager->updateStakeholderPortfolio($request->get('data'));
                                                break;
                                            }

                                            if(is_array($result)){
                                                return $responder($result);
                                            }else{
                                                return $responder(false,'Invalid request...');
                                            }
                                        break;
                                    }
                                break;
                            }
                            return $responder(true,'');
                        }
                        return $responder(false,'Invalid request');
                    break;
                }
            }
        }

        /*Handling pages that rejects auth */
        if(isset(array_flip($pagesThatRejectsAuth)[$page])){
            if($isLogged){
                if($request->isGet()){
                    $this->setContinueUrl();
                    $this->setPageNotification('error','Ooops please logout to continue to that page...');
                    return $response->redirect('/user/dashboard');
                }else{
                    $this->setPageNotification('error','Ooops please logout to save data');
                    return $response->redirect('/user/dashboard');
                }
            }else{
                if($internal || $request->isGet()){
                    switch($page){
                        case 'home':
                            $addData('data-->header-->title','Scit HomePage');
                            $addData('data-->user-->login',[
                                'url' => '/user/login',
                                'token' => $this->getTokenFor('user-login')
                            ]);
                            return $response->write($htmlManager->render('index.html',$getData()));
                        break;

                        case 'user-login':
                            $addData('data-->header-->title','Scit User Login');
                            $addData('data-->header-->nav-->active','login');
                            $addData('data-->user-->login',[
                                'url' => '/user/login',
                                'token' => $this->getTokenFor('user-login')
                            ]);
                            return $response->write($htmlManager->render('user/login.html',$getData()));
                        break;

                        case 'user-register':
                            $addData('data-->header-->title','Scit User Register');
                            $addData('data-->header-->nav-->active','register');
                            $addData('data-->countries',$this->getUtils()->init('Users-Admin-Manager')->get('countries-list',[
                                'limit' => 20000
                            ]));
                            $addData('data-->user-->register',[
                                'url' => '/user/register',
                                'token' => $this->getTokenFor('user-register')
                            ]);
                            $addData('data-->regions',[
                                'url' => '/get/regions',
                                'token' => $this->getTokenFor('get-regions')
                            ]);
                            $addData('data-->lgas',[
                                'url' => '/get/lgas',
                                'token' => $this->getTokenFor('get-lgas')
                            ]);
                            $addData('data-->schools',[
                                'url' => '/get/secondary-schools',
                                'token' => $this->getTokenFor('get-secondary-schools')
                            ]);
                            return $response->write($htmlManager->render('user/register.html',$getData()));
                        break;

                        case 'user-forgot-password':
                            $addData('data-->header-->title','Scit User Password Reset');
                            $addData('data-->header-->nav-->active','forgot-password');
                            $addData('data-->user-->forgot_password',[
                                'url' => '/user/forgot-password',
                                'token' => $this->getTokenFor('user-forgot-password')
                            ]);
                            return $response->write($htmlManager->render('user/reset.html',$getData()));
                        break;
                    }
                }

                if($request->isPost()){
                    switch($page){
                        case 'user-login':
                            $result = $userManager->login();

                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                $responseString = $this->getUtils()::getDataFromArray($result,'response');
                                $from = $this->getUtils()::getDataFromArray($result,'from') ?: 'general';

                                if($status === 'ok'){
                                    return $response->redirect($this->getContinueUrlAfter($page));
                                }

                                if($status === 'error'){
                                    if($from === 'general' && !is_array($response) && is_string($responseString)){
                                        $this->setPageNotification('error',$responseString);
                                    }else{
                                        $this->set('errorResult',$result);
                                    }
                                }
                            }else{
                                $this->setPageNotification('error','Ooops invalid response');
                            }

                            return $this->process($page,true);
                        break;

                        case 'user-register':
                            $result = $userManager->register();
                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');

                                if($status === 'ok'){
                                    return $response->redirect($this->getContinueUrlAfter($page));
                                }

                                if($status === 'error'){
                                    $from = $this->getUtils()::getDataFromArray($result,'from');
                                    $response = $this->getUtils()::getDataFromArray($result,'response');

                                    if(($from === 'general') && is_string($response)){
                                        $this->setPageNotification('error',$response);
                                    }else{
                                        $this->set('errorResult',$result);
                                    }
                                }
                            }else{
                                $this->setPageNotification('error','Ooops invalid response');
                            }
                            return $this->process($page,true);
                        break;

                        case 'user-forgot-password':

                        break;
                    }
                }
            }
        }

        /*Handling pages that requires auth */
        if(isset(array_flip($pagesThatRequireAuth)[$page])){
            if(!$isLogged){
                if($request->isGet()){
                    $this->setContinueUrl();
                    $this->setPageNotification('error','Ooops you must be logged to continue to that page...');
                    return $response->redirect('/user/login');
                }
            }else{
                $userManager->updateUserData();
                $session = $this->getSession();
                $userType = $session->get('userData-->type');
                $isVerified = $session->get('userData-->isVerified');
                $hasCompleteRegistration = $session->get('userData-->hasCompleteRegistration');
                $isApproved = $session->get('userData-->isApproved');

                if($internal || $request->isGet()){
                    if(!$internal){
                        switch(true){
                            case (!$isVerified):
                                return (($page === 'user-account-verification') ? $this->process($page,true) : $response->redirect('/user/account/verification'));
                            break;

                            case (!$hasCompleteRegistration):
                                return (($page === 'user-complete-registration') ? $this->process($page,true) : $response->redirect('/user/complete/registration'));
                            break;

                            case (!$isApproved):
                                return (($page === 'user-account-approval') ? $this->process($page,true) : $response->redirect('/user/account/approval'));
                            break;
                        }

                        if($isVerified && $hasCompleteRegistration && $isApproved){
                            $restricted = 0;

                            switch(true){
                                case (substr($page,0,strlen('general-administrator')) === 'general-administrator'):
                                    if($userManager->hasPermissionAs('general-administrator')){

                                    }else{
                                        $restricted = 1;
                                    }
                                break;

                                case (substr($page,0,strlen('support-administrator')) === 'support-administrator'):
                                    if($userManager->hasPermissionAs('support-administrator')){

                                    }else{
                                        $restricted = 1;
                                    }
                                break;

                                case (substr($page,0,strlen('secondary-school-administrator')) === 'secondary-school-administrator'):
                                    if($userManager->hasPermissionAs('secondary-school-administrator')){

                                    }else{
                                        $restricted = 1;
                                    }
                                break;

                                case (substr($page,0,strlen('institution-administrator')) === 'institution-administrator'):
                                    if($userManager->hasPermissionAs('institution-administrator')){

                                    }else{
                                        $restricted = 1;
                                    }
                                break;

                                case (substr($page,0,strlen('internship-provider-administrator')) === 'internship-provider-administrator'):
                                    if($userManager->hasPermissionAs('internship-provider-administrator')){

                                    }else{
                                        $restricted = 1;
                                    }
                                break;

                                case (substr($page,0,strlen('student')) === 'student'):
                                    if($userManager->hasPermissionAs('student')){

                                    }else{
                                        $restricted = 1;
                                    }
                                break;

                                case (substr($page,0,strlen('general')) === 'general'):
                                    switch(true){
                                        case isset(array_flip([
                                            'general-view-faq',
                                            'general-view-faqs',
                                            'general-add-faq'
                                        ])[$page]):
                                            if(!in_array(true,[
                                                $userManager->hasPermissionAs('general-administrator'),
                                                $userManager->hasPermissionAs('support-administrator')
                                            ],true)){
                                                $restricted = 1;
                                            }
                                        break;
                                    }
                                break;
                            }

                            if($restricted){
                                return $this->process('restricted-access',true);
                            }
                        }
                    }

                    switch($page){
                        case 'get-data':
                            return $response->redirect('/404',302);
                        break;

                        case 'user-account-verification':
                            if(!$isVerified){
                                $addData('data-->header-->title','Scit User Account Verification');
                                $addData('data-->user-->verification',[
                                    'url' => $request->getFullRequestUrl(),
                                    'token' => $this->getTokenFor('user-account-verification'),
                                    'for' => 'resend'
                                ]);

                                $hash = $request->getQueryDataFor('withToken');
                                if($hash){
                                    $addData('data-->user-->verification-->for','verify');
                                    $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v2-->sitekey'));
                                    $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v2-->url'));
                                    $addData('data-->user-->verification-->hash',$hash);
                                }else{
                                    $email = $session->get('userData-->email');
                                    $verificationsData = $userManager->getEmailVerificationData();

                                    $status = $this->getUtils()::getDataFromArray($verificationsData,'status');
                                    $empty = $this->getUtils()::getDataFromArray($verificationsData,'empty');
                                    $result = $this->getUtils()::getDataFromArray($verificationsData,'response');

                                    if($status == 'error'){
                                        $this->setPageNotification('error',$result);
                                    }

                                    $addData('data-->user-->email-->verifications-->maximum',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->verification-->email-->maximum'));

                                    if($empty){
                                        $addData('data-->user-->email-->verification-->hasData',false);
                                        $addData('data-->user-->email-->verifications-->sent',0);
                                    }else{
                                        $addData('data-->user-->email-->verification-->hasData',true);
                                        $addData('data-->user-->email-->verifications-->sent',$this->getUtils()::getDataFromArray($verificationsData,'data-->total_sent'));
                                        $addData('data-->user-->email-->verification-->last_sent_at',$this->getUtils()::getDataFromArray($verificationsData,'data-->last_sent_at'));
                                        $addData('data-->user-->emailAddress',\str_pad(substr($email,0,5),strlen($email),'****************'));
                                    }
                                }
                                return $response->write($htmlManager->render('user/verifyAccount.html',$getData()));
                            }

                            $this->setPageNotification('error','Ooops... Your account has been verified already');
                            return $response->redirect('/user/dashboard');
                        break;

                        case 'user-complete-registration':
                            if(!$hasCompleteRegistration){
                                $addData('data-->header-->title','Scit User Complete Registration');
                                $addData('data-->countries',$this->getUtils()->init('Users-Admin-Manager')->get('countries-list',[
                                    'limit' => 20000
                                ]));

                                $userType = $session->get('userData-->type');
                                $stakeholderType = \str_replace('-administrator','',$userType);

                                $addData('data-->user-->addStakeholderData',[
                                    'url' => '/user/complete/registration',
                                    'token' => $this->getTokenFor('user-complete-registration'),
                                    'for' => $stakeholderType
                                ]);

                                $addData('data-->regions',[
                                    'url' => '/get/regions',
                                    'token' => $this->getTokenFor('get-regions')
                                ]);
                                $addData('data-->lgas',[
                                    'url' => '/get/lgas',
                                    'token' => $this->getTokenFor('get-lgas')
                                ]);
                                $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                                $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));
                                return $response->write($htmlManager->render('user/addStakeholderData.html',$getData()));
                            }

                            $this->setPageNotification('error','Ooops... You account is fully registered');
                            return $response->redirect('/user/dashboard');
                        break;

                        case 'user-account-approval':
                            if(!$isApproved){
                                $addData('data-->header-->title','Scit User Account Approval');
                                $addData('data-->user-->type',$this->getSession()->get('userData-->type'));
                                return $response->write($htmlManager->render('user/approveAccount.html',$getData()));
                            }

                            $this->setPageNotification('error','Ooops... Your account has been approved already');
                            return $response->redirect('/user/dashboard');
                        break;

                        case 'user-dashboard-home':
                            return $response->redirect("/{$userType}");
                        break;

                        case 'general-administrator-dashboard-home':
                            $userAdminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $data = $userAdminManager->get('general-administrator-dashboard-data');

                            $filters = [
                                [
                                    'compareUsing' => 'greater than',
                                    'pointer' => 'supportTicketId',
                                    'value' => 0
                                ]
                            ];

                            $orders = [
                                [
                                    'pointer' => 'supportTicketId',
                                    'type' => 'desc'
                                ]
                            ];

                            $result = $userAdminManager->get('support-tickets-list',[
                                'limit' => 3,
                                'from' => 0,
                                'filters' => $filters,
                                'orders' => $orders
                            ]);

                            $data['tickets'] = $result;
                            $addData('data-->dashboard-->data',$data);
                            return $response->write($htmlManager->render('user/general-administrator/dashboard/index.html',$getData()));
                        break;

                        case 'support-administrator-dashboard-home':
                            $userAdminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $data = $userAdminManager->get('general-administrator-dashboard-data');

                            $filters = [
                                [
                                    'compareUsing' => 'greater than',
                                    'pointer' => 'supportTicketId',
                                    'value' => 0
                                ]
                            ];

                            $orders = [
                                [
                                    'pointer' => 'supportTicketId',
                                    'type' => 'desc'
                                ]
                            ];

                            $result = $userAdminManager->get('support-tickets-list',[
                                'limit' => 3,
                                'from' => 0,
                                'filters' => $filters,
                                'orders' => $orders
                            ]);

                            $data['tickets'] = $result;
                            $addData('data-->dashboard-->data',$data);
                            return $response->write($htmlManager->render('user/support-administrator/dashboard/index.html',$getData()));
                        break;

                        case 'secondary-school-administrator-dashboard-home':
                            return $response->write($htmlManager->render('user/secondary-school-administrator/dashboard/index.html',$getData()));
                        break;

                        case 'internship-provider-administrator-dashboard-home':
                            return $response->write($htmlManager->render('user/internship-provider-administrator/dashboard/index.html',$getData()));
                        break;

                        case 'institution-administrator-dashboard-home':
                            return $response->write($htmlManager->render('user/institution-administrator/dashboard/index.html',$getData()));
                        break;

                        case 'general-administrator-add-subject':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin Add Subject');
                            $addData('data-->admin-->add-->subject',[
                                'url' => '/general-administrator/add/subject',
                                'token' => $this->getTokenFor('general-administrator-add-subject')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $professions = $adminManager->get('professions-list',[
                                'from' => 0,
                                'limit' => 10000
                            ]);
                            $addData('data-->professions',$professions);

                            if($request->isPost()){
                                $errorsDb = $this->get('adminAddSubject-->errors',true);
                                $existsDb = $this->get('adminAddSubject-->exists',true);
                                $invalidsDb = $this->get('adminAddSubject-->invalids',true);

                                if(is_array($errorsDb)){
                                    $addData('data-->admin-->add-->subject-->errors',$errorsDb);
                                }
                                if(is_array($existsDb)){
                                    $addData('data-->admin-->add-->subject-->exists',$existsDb);
                                }
                                if(is_array($invalidsDb)){
                                    $addData('data-->admin-->add-->subject-->invalids',$invalidsDb);
                                }
                            }
                            return $response->write($htmlManager->render('user/general-administrator/subject/add.html',$getData()));
                        break;

                        case 'general-administrator-view-subjects':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin View Subjects');
                            $addData('data-->subjectsModify',[
                                'url' => '/general-administrator/view/subjects',
                                'token' => $this->getTokenFor('general-administrator-view-subjects')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $from = ((int) $request->getQueryDataFor('from'));
                            $limit = 50;
                            $requestData = [
                                'from' => $from,
                                'limit' => $limit,
                                'filters' => [
                                    [
                                        'pointer' => 'subjectId',
                                        'value' => 0,
                                        'compareUsing' => 'greater than'
                                    ]
                                ]
                            ];

                            $subjects = $adminManager->get('subjects-list',$requestData);
                            foreach($subjects as &$subject){
                                $this->getUtils()::setDataInArray($subject,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ])->changePath('/general-administrator/view/subject')->changeQuery([
                                    'subjectId' => $subject['id']
                                ])->getUrlString());
                            }

                            $totalSubjects = $adminManager->get('subjects-count',$requestData);
                            $addData('data-->subjects-->list',$subjects);
                            $addData('data-->subjects-->total-->count',$totalSubjects);
                            $addData('data-->subjects-->from',$from);
                            $addData('data-->subjects-->increment',$limit);
                            return $response->write($htmlManager->render('user/general-administrator/subject/view.html',$getData()));
                        break;

                        case 'general-administrator-view-subject':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $subjectId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('subjectId'));
                            $subjectName = $request->get('subjectName');

                            if(!(($subjectId && is_numeric($subjectId) && ($subjectId = $adminManager->get('subject-id-from-id',[
                                'id' => $subjectId
                            ]))) || ($subjectName && ($subjectId = $adminManager->get('subject-id-from-name',[
                                'name' => $subjectName
                            ]))))){
                                $this->setPageNotification('error','Ooops invalid subject.');
                                return $response->redirect('/general-administrator/view/subjects');
                            }

                            $addData('data-->subjectModify',[
                                'url' => $request->getFullRequestUrl(),
                                'token' => $this->getTokenFor('general-administrator-view-subject')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $subjectData = $adminManager->get('subject-data',[
                                'subjectId' => $subjectId
                            ]);

                            $subjectProfessions = $adminManager->get('subject-professions-list',[
                                'subjectId' => $subjectId
                            ]);

                            $professions = $adminManager->get('professions-list',[
                                'from' => 0,
                                'limit' => 10000
                            ]);
                            $professionsData = [];

                            if(count($subjectProfessions)){
                                foreach($subjectProfessions as &$subjectProfession){
                                    $subjectProfession['weight'] = (int) $subjectProfession['weight'];

                                    $this->getUtils()::setDataInArray($professionsData,'selected-->list[]',$subjectProfession['id']);
                                    $this->getUtils()::setDataInArray($professionsData,"selected-->data-->{$subjectProfession['id']}",[
                                        'weight' => $subjectProfession['weight']
                                    ]);
                                }
                            }

                            $addData('data-->subject',$subjectData);
                            $addData('data-->professions',$professions);
                            $addData('data-->subject-->professions',$professionsData);


                            $addData('data-->header-->title',"Scit Admin View Subject -- {$subjectData['name']}");
                            return $response->write($htmlManager->render('user/general-administrator/subject/edit.html',$getData()));
                        break;

                        case 'general-administrator-add-discipline':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin Add Discipline');
                            $addData('data-->admin-->add-->discipline',[
                                'url' => '/general-administrator/add/discipline',
                                'token' => $this->getTokenFor('general-administrator-add-discipline')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $professions = $adminManager->get('professions-list',[
                                'from' => 0,
                                'limit' => 10000
                            ]);
                            $addData('data-->professions',$professions);

                            if($request->isPost()){
                                $errorsDb = $this->get('adminAddDiscipline-->errors',true);
                                $existsDb = $this->get('adminAddDiscipline-->exists',true);
                                $invalidsDb = $this->get('adminAddDiscipline-->invalids',true);

                                if(is_array($errorsDb)){
                                    $addData('data-->admin-->add-->discipline-->errors',$errorsDb);
                                }
                                if(is_array($existsDb)){
                                    $addData('data-->admin-->add-->discipline-->exists',$existsDb);
                                }
                                if(is_array($invalidsDb)){
                                    $addData('data-->admin-->add-->discipline-->invalids',$invalidsDb);
                                }
                            }
                            return $response->write($htmlManager->render('user/general-administrator/discipline/add.html',$getData()));
                        break;

                        case 'general-administrator-view-disciplines':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin View Disciplines');
                            $addData('data-->disciplinesModify',[
                                'url' => '/general-administrator/view/disciplines',
                                'token' => $this->getTokenFor('general-administrator-view-disciplines')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $from = ((int) $request->getQueryDataFor('from'));
                            $limit = 50;
                            $requestData = [
                                'from' => $from,
                                'limit' => $limit,
                                'filters' => [
                                    [
                                        'pointer' => 'disciplineId',
                                        'value' => 0,
                                        'compareUsing' => 'greater than'
                                    ]
                                ]
                            ];

                            $disciplines = $adminManager->get('disciplines-list',$requestData);
                            foreach($disciplines as &$discipline){
                                $this->getUtils()::setDataInArray($discipline,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ])->changePath('/general-administrator/view/discipline')->changeQuery([
                                    'disciplineId' => $discipline['id']
                                ])->getUrlString());
                            }

                            $totalDisciplines = $adminManager->get('disciplines-count',$requestData);
                            $addData('data-->disciplines-->list',$disciplines);
                            $addData('data-->disciplines-->total-->count',$totalDisciplines);
                            $addData('data-->disciplines-->from',$from);
                            $addData('data-->disciplines-->increment',$limit);
                            return $response->write($htmlManager->render('user/general-administrator/discipline/view.html',$getData()));
                        break;

                        case 'general-administrator-view-discipline':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $disciplineId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('disciplineId'));
                            $disciplineName = $request->get('disciplineName');

                            if(!(($disciplineId && is_numeric($disciplineId) && ($disciplineId = $adminManager->get('discipline-id-from-id',[
                                'id' => $disciplineId
                            ]))) || ($disciplineName && ($disciplineId = $adminManager->get('discipline-id-from-name',[
                                'name' => $disciplineName
                            ]))))){
                                $this->setPageNotification('error','Ooops invalid discipline.');
                                return $response->redirect('/general-administrator/view/disciplines');
                            }

                            $addData('data-->disciplineModify',[
                                'url' => $request->getFullRequestUrl(),
                                'token' => $this->getTokenFor('general-administrator-view-discipline')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $disciplineData = $adminManager->get('discipline-data',[
                                'disciplineId' => $disciplineId
                            ]);

                            $disciplineProfessions = $adminManager->get('discipline-professions-list',[
                                'disciplineId' => $disciplineId
                            ]);

                            $professions = $adminManager->get('professions-list',[
                                'from' => 0,
                                'limit' => 10000
                            ]);
                            $professionsData = [];

                            if(count($disciplineProfessions)){
                                foreach($disciplineProfessions as &$disciplineProfession){
                                    $disciplineProfession['weight'] = (int) $disciplineProfession['weight'];

                                    $this->getUtils()::setDataInArray($professionsData,'selected-->list[]',$disciplineProfession['id']);
                                    $this->getUtils()::setDataInArray($professionsData,"selected-->data-->{$disciplineProfession['id']}",[
                                        'weight' => $disciplineProfession['weight']
                                    ]);
                                }
                            }

                            $addData('data-->discipline',$disciplineData);
                            $addData('data-->professions',$professions);
                            $addData('data-->discipline-->professions',$professionsData);


                            $addData('data-->header-->title',"Scit Admin View Discipline -- {$disciplineData['name']}");
                            return $response->write($htmlManager->render('user/general-administrator/discipline/edit.html',$getData()));
                        break;

                        case 'general-administrator-add-profession':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin Add Profession');
                            $addData('data-->admin-->add-->profession',[
                                'url' => '/general-administrator/add/profession',
                                'token' => $this->getTokenFor('general-administrator-add-profession')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $addData('data-->disciplines',$adminManager->get('disciplines-list',[
                                'from' => 0,
                                'limit' => 1000000
                            ]));

                            if($request->isPost()){
                                $errorsDb = $this->get('adminAddProfession-->errors',true);
                                $existsDb = $this->get('adminAddProfession-->exists',true);
                                $invalidsDb = $this->get('adminAddProfession-->invalids',true);

                                if(is_array($errorsDb)){
                                    $addData('data-->admin-->add-->profession-->errors',$errorsDb);
                                }
                                if(is_array($existsDb)){
                                    $addData('data-->admin-->add-->profession-->exists',$existsDb);
                                }
                                if(is_array($invalidsDb)){
                                    $addData('data-->admin-->add-->profession-->invalids',$invalidsDb);
                                }
                            }
                            return $response->write($htmlManager->render('user/general-administrator/profession/add.html',$getData()));
                        break;

                        case 'general-administrator-view-professions':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin View Professions');
                            $addData('data-->professionsModify',[
                                'url' => '/general-administrator/view/professions',
                                'token' => $this->getTokenFor('general-administrator-view-professions')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $from = ((int) $request->getQueryDataFor('from'));
                            $limit = 50;
                            $requestData = [
                                'from' => $from,
                                'limit' => $limit,
                                'filters' => [
                                    [
                                        'pointer' => 'professionId',
                                        'value' => 0,
                                        'compareUsing' => 'greater than'
                                    ]
                                ]
                            ];

                            $professions = $adminManager->get('professions-list',$requestData);
                            foreach($professions as &$profession){
                                $this->getUtils()::setDataInArray($profession,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ])->changePath('/general-administrator/view/profession')->changeQuery([
                                    'professionId' => $profession['id']
                                ])->getUrlString());
                            }

                            $totalProfessions = $adminManager->get('professions-count',$requestData);
                            $addData('data-->professions-->list',$professions);
                            $addData('data-->professions-->total-->count',$totalProfessions);
                            $addData('data-->professions-->from',$from);
                            $addData('data-->professions-->increment',$limit);
                            return $response->write($htmlManager->render('user/general-administrator/profession/view.html',$getData()));
                        break;

                        case 'general-administrator-view-profession':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $professionId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('professionId'));
                            $professionName = $request->get('professionName');

                            if(!(($professionId && is_numeric($professionId) && ($professionId = $adminManager->get('profession-id-from-id',[
                                'id' => $professionId
                            ]))) || ($professionName && ($professionId = $adminManager->get('profession-id-from-name',[
                                'name' => $professionName
                            ]))))){
                                $this->setPageNotification('error','Ooops invalid profession.');
                                return $response->redirect('/general-administrator/view/professions');
                            }

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $professionData = $adminManager->get('profession-data',[
                                'professionId' => $professionId
                            ]);

                            $professionDisciplines = $adminManager->get('profession-disciplines-list',[
                                'professionId' => $professionId
                            ]);

                            $disciplines = $adminManager->get('disciplines-list',[
                                'from' => 0,
                                'limit' => 100000
                            ]);
                            $disciplinesData = [];

                            if(count($professionDisciplines)){
                                foreach($professionDisciplines as &$professionDiscipline){
                                    $professionDiscipline['weight'] = (int) $professionDiscipline['weight'];

                                    $this->getUtils()::setDataInArray($disciplinesData,'selected-->list[]',$professionDiscipline['id']);
                                    $this->getUtils()::setDataInArray($disciplinesData,"selected-->data-->{$professionDiscipline['id']}",[
                                        'weight' => $professionDiscipline['weight']
                                    ]);
                                }
                            }

                            $addData('data-->profession',$professionData);
                            $addData('data-->disciplines',$disciplines);
                            $addData('data-->profession-->disciplines',$disciplinesData);

                            $addData('data-->professionModify',[
                                'url' => $request->getFullRequestUrl(),
                                'token' => $this->getTokenFor('general-administrator-view-profession')
                            ]);

                            return $response->write($htmlManager->render('user/general-administrator/profession/edit.html',$getData()));
                        break;

                        case 'general-administrator-add-temperament':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin Add Temperament');
                            $addData('data-->admin-->add-->temperament',[
                                'url' => '/general-administrator/add/temperament',
                                'token' => $this->getTokenFor('general-administrator-add-temperament')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $professions = $adminManager->get('professions-list',[
                                'from' => 0,
                                'limit' => 10000
                            ]);
                            $addData('data-->professions',$professions);

                            if($request->isPost()){
                                $errorsDb = $this->get('adminAddTemperament-->errors',true);
                                $existsDb = $this->get('adminAddTemperament-->exists',true);
                                $invalidsDb = $this->get('adminAddTemperament-->invalids',true);

                                if(is_array($errorsDb)){
                                    $addData('data-->admin-->add-->temperament-->errors',$errorsDb);
                                }
                                if(is_array($existsDb)){
                                    $addData('data-->admin-->add-->temperament-->exists',$existsDb);
                                }
                                if(is_array($invalidsDb)){
                                    $addData('data-->admin-->add-->temperament-->invalids',$invalidsDb);
                                }
                            }
                            return $response->write($htmlManager->render('user/general-administrator/temperament/add.html',$getData()));
                        break;

                        case 'general-administrator-view-temperaments':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin View Temperaments');
                            $addData('data-->temperamentsModify',[
                                'url' => '/general-administrator/view/temperaments',
                                'token' => $this->getTokenFor('general-administrator-view-temperaments')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $from = ((int) $request->getQueryDataFor('from'));
                            $limit = 50;
                            $requestData = [
                                'from' => $from,
                                'limit' => $limit,
                                'filters' => [
                                    [
                                        'pointer' => 'disciplineId',
                                        'value' => 0,
                                        'compareUsing' => 'greater than'
                                    ]
                                ]
                            ];

                            $temperaments = $adminManager->get('temperaments-list',$requestData);
                            foreach($temperaments as &$temperament){
                                $this->getUtils()::setDataInArray($temperament,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ])->changePath('/general-administrator/view/temperament')->changeQuery([
                                    'temperamentId' => $temperament['id']
                                ])->getUrlString());
                            }

                            $totalTemperaments = $adminManager->get('temperaments-count',$requestData);
                            $addData('data-->temperaments-->list',$temperaments);
                            $addData('data-->temperaments-->total-->count',$totalTemperaments);
                            $addData('data-->temperaments-->from',$from);
                            $addData('data-->temperaments-->increment',$limit);
                            return $response->write($htmlManager->render('user/general-administrator/temperament/view.html',$getData()));
                        break;

                        case 'general-administrator-view-temperament':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $temperamentId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('temperamentId'));
                            $temperamentName = $request->get('temperamentName');

                            if(!(($temperamentId && is_numeric($temperamentId) && ($temperamentId = $adminManager->get('temperament-id-from-id',[
                                'id' => $temperamentId
                            ]))) || ($temperamentName && ($temperamentId = $adminManager->get('temperament-id-from-name',[
                                'name' => $temperamentName
                            ]))))){
                                $this->setPageNotification('error','Ooops invalid temperament.');
                                return $response->redirect('/general-administrator/view/temperaments');
                            }

                            $addData('data-->temperamentModify',[
                                'url' => $request->getFullRequestUrl(),
                                'token' => $this->getTokenFor('general-administrator-view-temperament')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $temperamentData = $adminManager->get('temperament-data',[
                                'temperamentId' => $temperamentId
                            ]);

                            $temperamentProfessions = $adminManager->get('temperament-professions-list',[
                                'temperamentId' => $temperamentId
                            ]);

                            $professions = $adminManager->get('professions-list',[
                                'from' => 0,
                                'limit' => 10000
                            ]);
                            $professionsData = [];

                            if(count($temperamentProfessions)){
                                foreach($temperamentProfessions as &$temperamentProfession){
                                    $this->getUtils()::setDataInArray($professionsData,'selected-->list[]',$temperamentProfession['id']);
                                }
                            }

                            $addData('data-->temperament',$temperamentData);
                            $addData('data-->professions',$professions);
                            $addData('data-->temperament-->professions',$professionsData);

                            $addData('data-->header-->title',"Scit Admin View Temperament -- {$temperamentData['name']}");
                            return $response->write($htmlManager->render('user/general-administrator/temperament/edit.html',$getData()));
                        break;

                        case 'general-administrator-view-approval-requests':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $for = $request->getQueryDataFor('for');

                            $userTypes = [
                                'secondary-schools',
                                'internship-providers',
                                'institutions',
                                'students',
                                'general-administrators',
                                'support-administrators',
                                'internship-provider-administrators',
                                'institution-administrators',
                                'secondary-school-administrators',
                            ];

                            if(isset(array_flip($userTypes)[$for])){
                                $addData('data-->approvalRequest',[
                                    'for' => $for,
                                    'url' => $request->getFullRequestUrl(),
                                    'token' => $this->getTokenFor($page)
                                ]);

                                $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                                $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                                $titleFor = ucwords(implode(' ',explode('-',$for)));
                                $addData('data-->header-->title',"Scit Admin {$titleFor} Approval Request List");
                                $list;

                                $from = ((int) $request->getQueryDataFor('from'));
                                $limit = 50;
                                $requestData;

                                switch(true){
                                    case (isset(array_flip([
                                        'institutions',
                                        'internship-providers'
                                    ])[$for])):
                                        $requestData = [
                                            'from' => $from,
                                            'limit' => $limit,
                                            'filters' => [
                                                [
                                                    'pointer' => 'stakeholderApprovalStatus',
                                                    'compareUsing' => 'equality',
                                                    'value' => 0
                                                ]
                                            ]
                                        ];
                                    break;

                                    case (isset(array_flip([
                                        'secondary-schools'
                                    ])[$for])):
                                        $requestData = [
                                            'from' => $from,
                                            'limit' => $limit,
                                            'filters' => [
                                                [
                                                    'pointer' => 'schoolApprovalStatus',
                                                    'compareUsing' => 'equality',
                                                    'value' => 0
                                                ]
                                            ]
                                        ];
                                    break;

                                    case (isset(array_flip([
                                        'general-administrators',
                                        'support-administrators',
                                        'internship-provider-administrators',
                                        'institution-administrators',
                                        'secondary-school-administrators'
                                    ])[$for])):
                                        $requestData = [
                                            'from' => $from,
                                            'limit' => $limit,
                                            'filters' => [
                                                [
                                                    'pointer' => 'adminApprovalStatus',
                                                    'compareUsing' => 'equality',
                                                    'value' => 0
                                                ]
                                            ]
                                        ];
                                    break;

                                    case (isset(array_flip([
                                        'students'
                                    ])[$for])):
                                        $requestData = [
                                            'from' => $from,
                                            'limit' => $limit,
                                            'filters' => [
                                                [
                                                    'pointer' => 'studentApprovalStatus',
                                                    'compareUsing' => 'equality',
                                                    'value' => 0
                                                ]
                                            ]
                                        ];
                                    break;
                                }


                                $list = $adminManager->get("{$for}-list",$requestData);
                                $addData("data-->{$for}-->list",$list);
                                $total = $adminManager->get("{$for}-count",$requestData);
                                $addData("data-->{$for}-->total-->count",$total);
                                $addData("data-->{$for}-->from",$from);
                                $addData("data-->{$for}-->increment",$limit);
                                $addData('data-->for',$for);
                                $addData('data-->update-data',[
                                    'token' => $this->getTokenFor('update-data')
                                ]);

                                return $response->write($htmlManager->render("user/management/lists/manager.html",$getData()));
                            }else{
                                if($for){
                                    $this->setPageNotification('error','Ooops invalid temperament.');
                                }

                                $userTypes = [
                                    'secondary-schools' => [
                                        'name' => 'Secondary Schools'
                                    ],
                                    'internship-providers' => [
                                        'name' => 'Internship Providers'
                                    ],
                                    'institutions' => [
                                        'name' => 'Institutions'
                                    ],
                                    'students' => [
                                        'name' => 'Students'
                                    ],
                                    'general-administrators' => [
                                        'name' => 'General Administrators'
                                    ],
                                    'support-administrators' => [
                                        'name' => 'Support Administrators'
                                    ],
                                    'internship-provider-administrators' => [
                                        'name' => 'Internship Provider Administrators'
                                    ],
                                    'institution-administrators' => [
                                        'name' => 'Institution Administrators'
                                    ],
                                    'secondary-school-administrators' => [
                                        'name' => 'Secondary School Administrators'
                                    ]
                                ];

                                foreach($userTypes as $key => &$userTypeData){
                                    $addData("data-->stakeholders-->list[]",[
                                        'name' => $userTypeData['name'],
                                        'url' => $this->getUtils()->init('General-UrlProcessor',[
                                            'url' => $request->getFullRequestUrl()
                                        ])->changeQuery([
                                            'for' => $key
                                        ])->getUrlString()
                                    ]);
                                }

                                $addData('data-->for','index');
                                return $response->write($htmlManager->render("user/management/approvals/manager.html",$getData()));
                            }
                        break;

                        case 'general-administrator-add-event':

                            $addData('data-->admin-->add-->event',[
                                'url' => '/general-administrator/add/event',
                                'token' => $this->getTokenFor($page)
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            return $response->write($htmlManager->render('user/general-administrator/event/add.html',$getData()));
                        break;

                        case 'general-administrator-view-events':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin View Events');
                            $addData('data-->eventsModify',[
                                'url' => '/general-administrator/view/events',
                                'token' => $this->getTokenFor('general-administrator-view-events')
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $from = ((int) $request->getQueryDataFor('from'));
                            $limit = 50;
                            $requestData = [
                                'from' => $from,
                                'limit' => $limit,
                                'filters' => [
                                    [
                                        'pointer' => 'eventId',
                                        'value' => 0,
                                        'compareUsing' => 'greater than'
                                    ]
                                ]
                            ];

                            $events = $adminManager->get('events-list',$requestData);

                            foreach($events as &$event){
                                $this->getUtils()::setDataInArray($event,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ])->changePath('/general-administrator/view/event')->changeQuery([
                                    'eventId' => $event['id']
                                ])->getUrlString());
                            }

                            $totalEvents = $adminManager->get('events-count',$requestData);
                            $addData('data-->events-->list',$events);
                            $addData('data-->events-->total-->count',$totalEvents);
                            $addData('data-->events-->from',$from);
                            $addData('data-->events-->increment',$limit);
                            return $response->write($htmlManager->render('user/general-administrator/event/view.html',$getData()));
                        break;

                        case 'general-administrator-view-event':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $addData('data-->header-->title','Scit Admin View Event');

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $addData('data-->eventModify',[
                                'url' => $request->getFullRequestUrl(),
                                'token' => $this->getTokenFor('general-administrator-view-event')
                            ]);

                            $eventId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('eventId'));

                            if(!($eventId = $adminManager->get('event-id-from-id',[
                                'id' => $eventId
                            ]))){
                                $this->setPageNotification('error','Ooops invalid event.');
                                return $response->redirect('/general-administrator/view/events');
                            }

                            $eventData = $adminManager->get('event-data',[
                                'eventId' => $eventId
                            ]);

                            $addData('data-->event',$eventData);
                            return $response->write($htmlManager->render('user/general-administrator/event/edit.html',$getData()));
                        break;

                        case 'general-administrator-messenger':
                            $userManager = $this->getUtils()->init('Users-Manager');
                            $chatData = $userManager->getChatMetaData();

                            $addData('data-->chat-->fromList',$this->getUtils()::getDataFromArray($chatData,'fromList'));
                            $addData('data-->chat-->sendList',$this->getUtils()::getDataFromArray($chatData,'sendList'));
                            return $response->write($htmlManager->render('user/general-administrator/chat/index.html',$getData()));
                        break;

                        case 'handle-chat-request':
                            $userManager = $this->getUtils()->init('Users-Manager');
                            $result = $userManager->handleChatRequest();
                            return $responder($result);
                        break;

                        case 'general-administrator-view-general-administrators':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("general-administrators-list",$requestData);
                            $addData("data-->general-administrators-->list",$list);
                            $total = $adminManager->get("general-administrators-count",$requestData);
                            $addData("data-->general-administrators-->total-->count",$total);
                            $addData("data-->general-administrators-->from",$from);
                            $addData("data-->general-administrators-->increment",$limit);
                            $addData('data-->for','general-administrators');

                            $addData('data-->header-->title',"Scit Admin View General Administrators");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-support-administrators':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $userAdminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("support-administrators-list",$requestData);
                            $addData("data-->support-administrators-->list",$list);
                            $total = $adminManager->get("support-administrators-count",$requestData);
                            $addData("data-->support-administrators-->total-->count",$total);
                            $addData("data-->support-administrators-->from",$from);
                            $addData("data-->support-administrators-->increment",$limit);
                            $addData('data-->for','support-administrators');

                            $addData('data-->header-->title',"Scit Admin View Support Administrators");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-internship-provider-administrators':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("internship-provider-administrators-list",$requestData);
                            $addData("data-->internship-provider-administrators-->list",$list);
                            $total = $adminManager->get("internship-provider-administrators-count",$requestData);
                            $addData("data-->internship-provider-administrators-->total-->count",$total);
                            $addData("data-->internship-provider-administrators-->from",$from);
                            $addData("data-->internship-provider-administrators-->increment",$limit);
                            $addData('data-->for','internship-provider-administrators');

                            $addData('data-->header-->title',"Scit View Internship Providers Administrators");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-institution-administrators':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("institution-administrators-list",$requestData);
                            $addData("data-->institution-administrators-->list",$list);
                            $total = $adminManager->get("institution-administrators-count",$requestData);
                            $addData("data-->institution-administrators-->total-->count",$total);
                            $addData("data-->institution-administrators-->from",$from);
                            $addData("data-->institution-administrators-->increment",$limit);
                            $addData('data-->for','institution-administrators');

                            $addData('data-->header-->title',"Scit Admin View Institution Administrators");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-secondary-school-administrators':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("secondary-school-administrators-list",$requestData);
                            $addData("data-->secondary-school-administrators-->list",$list);
                            $total = $adminManager->get("secondary-school-administrators-count",$requestData);
                            $addData("data-->secondary-school-administrators-->total-->count",$total);
                            $addData("data-->secondary-school-administrators-->from",$from);
                            $addData("data-->secondary-school-administrators-->increment",$limit);
                            $addData('data-->for','secondary-school-administrators');

                            $addData('data-->header-->title',"Scit Admin View Secondary School Administrators");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-students':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("students-list",$requestData);
                            $addData("data-->students-->list",$list);
                            $total = $adminManager->get("students-count",$requestData);
                            $addData("data-->students-->total-->count",$total);
                            $addData("data-->students-->from",$from);
                            $addData("data-->students-->increment",$limit);
                            $addData('data-->for','students');

                            $addData('data-->header-->title',"Scit Admin View Students");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-internship-providers':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("internship-providers-list",$requestData);
                            $addData("data-->internship-providers-->list",$list);
                            $total = $adminManager->get("internship-providers-count",$requestData);
                            $addData("data-->internship-providers-->total-->count",$total);
                            $addData("data-->internship-providers-->from",$from);
                            $addData("data-->internship-providers-->increment",$limit);
                            $addData('data-->for','internship-providers');

                            $addData('data-->header-->title',"Scit Admin View Internship Providers");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-institutions':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("institutions-list",$requestData);
                            $addData("data-->institutions-->list",$list);
                            $total = $adminManager->get("institutions-count",$requestData);
                            $addData("data-->institutions-->total-->count",$total);
                            $addData("data-->institutions-->from",$from);
                            $addData("data-->institutions-->increment",$limit);
                            $addData('data-->for','institutions');

                            $addData('data-->header-->title',"Scit Admin View Institutions");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'general-administrator-view-secondary-schools':
                            $userAdminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    /*[
                                        'pointer' => 'adminName',
                                        'value' => 'okafor',
                                        'compareUsing' => 'like'
                                    ]*/
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $userAdminManager->get("secondary-schools-list",$requestData);
                            $addData("data-->secondary-schools-->list",$list);
                            $total = $userAdminManager->get("secondary-schools-count",$requestData);
                            $addData("data-->secondary-schools-->total-->count",$total);
                            $addData("data-->secondary-schools-->from",$from);
                            $addData("data-->secondary-schools-->increment",$limit);
                            $addData('data-->for','secondary-schools');

                            $addData('data-->header-->title',"Scit Admin View Secondary Schools");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'secondary-school-administrator-view-students':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    [
                                        'pointer' => 'schoolId',
                                        'compareUsing' => 'equality',
                                        'value' => $this->getSession()->get('adminData-->secondary-school-administrator-->stakeholderId')
                                    ]
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("students-list",$requestData);
                            $addData("data-->students-->list",$list);
                            $total = $adminManager->get("students-count",$requestData);
                            $addData("data-->students-->total-->count",$total);
                            $addData("data-->students-->from",$from);
                            $addData("data-->students-->increment",$limit);
                            $addData('data-->for','students');

                            $addData('data-->header-->title',"Scit Secondary School Students");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'secondary-school-administrator-view-applicants':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $requestData = [
                                'filters' => [
                                    [
                                        'pointer' => 'schoolId',
                                        'compareUsing' => 'equality',
                                        'value' => $this->getSession()->get('adminData-->secondary-school-administrator-->stakeholderId')
                                    ],
                                    [
                                        'pointer' => 'studentApprovalStatus',
                                        'compareUsing' => 'equality',
                                        'value' => 0
                                    ]
                                ],
                                'from' => $from,
                                'limit' => $limit
                            ];

                            $list = $adminManager->get("students-list",$requestData);
                            $addData("data-->students-->list",$list);
                            $total = $adminManager->get("students-count",$requestData);
                            $addData("data-->students-->total-->count",$total);
                            $addData("data-->students-->from",$from);
                            $addData("data-->students-->increment",$limit);
                            $addData('data-->for','students');

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $addData('data-->header-->title',"Scit Secondary School Students List");
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'internship-provider-administrator-view-students':
                            $userAdminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);
                            $addData('data-->approvalRequest',[
                                'for' => 'students',
                                'url' => $request->getFullRequestUrl(),
                                'token' => $this->getTokenFor($page)
                            ]);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $addData('data-->header-->title',"Scit Internship Provider Students List");
                            $list = $userAdminManager->get('students-list',[
                                'filters' => [
                                    [
                                        'pointer' => 'internshipProviderId',
                                        'compareUsing' => 'equality',
                                        'value' => $this->getSession()->get('adminData-->internship-provider-administrator-->stakeholderId')
                                    ]
                                ]
                            ]);

                            $addData('data-->students-->list',$list);
                            $addData('data-->for','students');
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'internship-provider-administrator-view-applicants':
                            $userAdminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $limit = 50;
                            $from = ((int) $request->getQueryDataFor('from') ?: 0);

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            $addData('data-->header-->title',"Scit Internship Provider Students Approval List");
                            $list = $userAdminManager->get('students-list',[
                                'filters' => [
                                    [
                                        'pointer' => 'internshipProviderId',
                                        'compareUsing' => 'equality',
                                        'value' => $this->getSession()->get('adminData-->internship-provider-administrator-->stakeholderId')
                                    ],
                                    [
                                        'pointer' => 'studentInternshipApprovalStatus',
                                        'compareUsing' => 'equality',
                                        'value' => 0
                                    ]
                                ]
                            ]);

                            $addData('data-->students-->list',$list);
                            $addData('data-->for','students');
                            $addData('data-->update-data',[
                                'token' => $this->getTokenFor('update-data')
                            ]);
                            return $response->write($htmlManager->render('user/management/lists/manager.html',$getData()));
                        break;

                        case 'student-dashboard-home':
                            return $response->write($htmlManager->render('user/student/dashboard/index.html',$getData()));
                        break;

                        case 'student-take-test':
                            $studentManager = $this->getUtils()->init('Users-Student-Manager');
                            $addData('data-->header-->title','Scit Student Take Test');
                            $addData('data-->testSubmit',[
                                'url' => '/student/take-test',
                                'token' => $this->getTokenFor('student-take-test')
                            ]);

                            $section = $request->getQueryDataFor('section');
                            $addData('data-->forcedTestFor',$section);

                            $retakeTest = ($request->getQueryDataFor('action') == 'retakeTest');
                            if($retakeTest){
                                $this->getSession()->remove('studentData-->testData');
                                return $response->redirect('/student/take-test');
                            }

                            $addData('data-->googleCaptcha-->siteKey',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->sitekey'));
                            $addData('data-->googleCaptcha-->url',$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->google-->captcha-->v3-->url'));

                            if($this->getSession()->get('studentData-->testData-->temperament-->isValid')){
                                $from = (int) $request->getQueryDataFor('from') ?: 0;
                                $limit = 1000;
                                $addData('data-->subjects',$this->getUtils()->init('Users-Admin-Manager')->get('subjects-list',[
                                    'from' => $from,
                                    'limit' => $limit
                                ]));
                            }

                            $addData('data-->testData',$this->getSession()->get('studentData-->testData'));
                            if($request->isPost()){
                                $testResult = $this->get('studentTestResult',true);
                                $addData('data-->testResult',$testResult);
                            }

                            if($this->getSession()->get('studentData-->testData-->academic-->isValid') && $this->getSession()->get('studentData-->testData-->temperament-->isValid') && $this->getSession()->get('studentData-->testData-->bestSubjects-->isValid')){
                                $addData('data-->temperamentData',$studentManager->getTemperamentData());
                                $addData('data-->relatedProfessions',$studentManager->getProfessionsData());
                            }

                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            return $response->write($htmlManager->render('user/student/take-test/test.html',$getData()));
                        break;

                        case 'general-add-faq':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            if(!($userManager->hasPermissionAs('general-administrator') || $userManager->hasPermissionAs('support-administrator'))){
                                return $this->process('restricted-access',true);
                            }

                            $addData('data-->header-->title','Scit Admin Add Faq');
                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $addData('data-->for','add');
                            return $response->write($htmlManager->render('user/management/pages/faq/manager.html',$getData()));
                        break;

                        case 'general-view-faqs':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            if(!($userManager->hasPermissionAs('general-administrator') || $userManager->hasPermissionAs('support-administrator'))){
                                return $this->process('restricted-access',true);
                            }

                            $addData('data-->header-->title','Scit Admin View Faqs');
                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $from = ((int) $request->getQueryDataFor('from'));
                            $limit = 50;
                            $requestData = [
                                'from' => $from,
                                'limit' => $limit,
                                'filters' => [
                                    [
                                        'pointer' => 'faqId',
                                        'value' => 0,
                                        'compareUsing' => 'greater than'
                                    ]
                                ]
                            ];

                            $faqs = $adminManager->get('faqs-list',$requestData);
                            foreach($faqs as &$faq){
                                $this->getUtils()::setDataInArray($faq,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ])->addSubPath('../faq')->changeQuery([
                                    'faqId' => $faq['id']
                                ])->getUrlString());
                            }

                            $totalFaqs = $adminManager->get('faqs-count',$requestData);
                            $addData('data-->faqs-->list',$faqs);
                            $addData('data-->faqs-->total-->count',$totalFaqs);
                            $addData('data-->faqs-->from',$from);
                            $addData('data-->faqs-->increment',$limit);

                            $addData('data-->for','view');
                            return $response->write($htmlManager->render('user/management/pages/faq/manager.html',$getData()));
                        break;

                        case 'general-view-faq':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            if(!($userManager->hasPermissionAs('general-administrator') || $userManager->hasPermissionAs('support-administrator'))){
                                return $this->process('restricted-access',true);
                            }

                            $faqId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('faqId'));

                            if(!(($faqId && is_numeric($faqId) && ($faqId = $adminManager->get('faq-id-from-id',[
                                'id' => $faqId
                            ]))))){
                                $this->setPageNotification('error','Ooops invalid faq.');
                                return $response->redirect('./faqs');
                            }

                            $addData('data-->update-data',[
                                'url' => $request->getFullRequestUrl(),
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $faqData = $adminManager->get('faq-data',[
                                'faqId' => $faqId
                            ]);

                            $addData('data-->faq',$faqData);

                            $addData('data-->header-->title',"Scit Admin View Faq -- {$faqData['question']}");
                            $addData('data-->for','edit');
                            return $response->write($htmlManager->render('user/management/pages/faq/manager.html',$getData()));
                        break;

                        case 'general-add-support-ticket':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            if(!$userManager->hasPermissionAs($this->getSession()->get('userData-->type'))){
                                return $this->process('restricted-access',true);
                            }

                            $addData('data-->header-->title','Scit New Support Ticket');
                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $addData('data-->get-support-ticket-categories',[
                                'url' => '/get/support/ticket/categories',
                                'token' => $this->getTokenFor('get-support-ticket-categories')
                            ]);

                            $userType = $this->getSession()->get('userData-->type');
                            $addData('data-->ticket-->from',$this->getUtils()->getHashOfData($userType));

                            $fromId;
                            if($userType === 'student'){
                                $fromId = $this->getSession()->get('studentData-->id');
                            }else{
                                $fromId = $this->getSession()->get('adminData-->'.$userType.'-->id');
                            }
                            $addData('data-->ticket-->fromId',$this->getUtils()->getHashOfData($fromId));

                            $supportTicketPriorities = $adminManager->get('support-ticket-priorities-list',[
                                'filters' => [
                                    'pointer' => 'supportTicketPriorityId',
                                    'value' => 0,
                                    'compareUsing' => 'greater than'
                                ]
                            ]);
                            $addData('data-->ticket-->priorities',$supportTicketPriorities);

                            $addData('data-->for','add');
                            return $response->write($htmlManager->render('user/management/pages/ticket/manager.html',$getData()));
                        break;

                        case 'general-view-support-tickets':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            if(!$userManager->hasPermissionAs($this->getSession()->get('userData-->type'))){
                                return $this->process('restricted-access',true);
                            }

                            $addData('data-->header-->title','Scit View Support Tickets');
                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $userType = $this->getSession()->get('userData-->type');
                            $addData('data-->ticket-->from',$this->getUtils()->getHashOfData($userType));

                            $fromId;
                            if($userType === 'student'){
                                $fromId = $this->getSession()->get('studentData-->id');
                            }else{
                                $fromId = $this->getSession()->get('adminData-->'.$userType.'-->id');
                            }
                            $addData('data-->ticket-->fromId',$this->getUtils()->getHashOfData($fromId));

                            $from = ((int) $request->getQueryDataFor('from'));
                            $limit = 50;
                            $requestData = [
                                'from' => $from,
                                'limit' => $limit,
                                'filters' => [
                                    [
                                        'pointer' => 'supportTicketId',
                                        'value' => 0,
                                        'compareUsing' => 'greater than'
                                    ]
                                ]
                            ];

                            switch(true){
                                case isset(array_flip([
                                    'internship-provider-administrator',
                                    'institution-administrator',
                                    'secondary-school-administrator'
                                ])[$userType]):
                                    $requestData['filters'][] = [
                                        'pointer' => 'supportTicketAdminId',
                                        'value' => $fromId,
                                        'compareUsing' => 'equality'
                                    ];
                                break;

                                case isset(array_flip([
                                    'student'
                                ])[$userType]):
                                    $requestData['filters'][] = [
                                        'pointer' => 'supportTicketStudentId',
                                        'value' => $fromId,
                                        'compareUsing' => 'equality'
                                    ];
                                break;

                                default:
                                    if(!($userManager->hasPermissionAs('general-administrator') || $userManager->hasPermissionAs('support-administrator'))){
                                        $requestData['filters'][] = [
                                            'pointer' => 'supportTicketAdminId',
                                            'value' => $fromId,
                                            'compareUsing' => 'equality'
                                        ];
                                    }
                                break;
                            }

                            $tickets = $adminManager->get('support-tickets-list',$requestData);

                            foreach($tickets as &$ticket){
                                $this->getUtils()::setDataInArray($ticket,'edit-->url',$this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ])->addSubPath('../ticket')->changeQuery([
                                    'ticketId' => $ticket['id']
                                ])->getUrlString());
                            }

                            $totalTickets = $adminManager->get('support-tickets-count',$requestData);
                            $addData('data-->tickets-->list',$tickets);
                            $addData('data-->tickets-->total-->count',$totalTickets);
                            $addData('data-->tickets-->from',$from);
                            $addData('data-->tickets-->increment',$limit);

                            $addData('data-->for','view');
                            return $response->write($htmlManager->render('user/management/pages/ticket/manager.html',$getData()));
                        break;

                        case 'general-view-support-ticket':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $ticketId = $this->getUtils()->getDataOfHash($request->getQueryDataFor('ticketId'));

                            if(!$userManager->hasPermissionAs($this->getSession()->get('userData-->type'))){
                                return $this->process('restricted-access',true);
                            }

                            if(!(($ticketId && is_numeric($ticketId) && ($ticketId = $adminManager->get('ticket-id-from-id',[
                                'id' => $ticketId
                            ]))))){
                                $this->setPageNotification('error','Ooops invalid ticket.');
                                return $response->redirect('./tickets');
                            }

                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $ticketData = $adminManager->get('support-ticket-data',[
                                'ticketId' => $ticketId
                            ]);

                            $addData('data-->ticket',$ticketData);

                            $supportTicketPriorities = $adminManager->get('support-ticket-priorities-list',[
                                'filters' => [
                                    'pointer' => 'supportTicketPriorityId',
                                    'value' => 0,
                                    'compareUsing' => 'greater than'
                                ]
                            ]);
                            $addData('data-->ticket-->priorities',$supportTicketPriorities);

                            $addData('data-->get-support-ticket-categories',[
                                'url' => '/get/support/ticket/categories',
                                'token' => $this->getTokenFor('get-support-ticket-categories')
                            ]);

                            $addData('data-->header-->title',"Scit Edit Support ticket");
                            $addData('data-->for','edit');
                            return $response->write($htmlManager->render('user/management/pages/ticket/manager.html',$getData()));
                        break;

                        case 'general-view-user-portfolio':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            if(!$userManager->hasPermissionAs($this->getSession()->get('userData-->type'))){
                                return $this->process('restricted-access',true);
                            }

                            $userId = $request->get('id');
                            if($userId){
                                $userId = $this->getUtils()->getDataOfHash($userId);
                            }

                            $urlProcessor = $this->getUtils()->init('General-UrlProcessor',[
                                'url' => $request->getFullRequestUrl()
                            ]);

                            $urlProcessor->changePath($this->getSession()->get('userData-->type'))->addSubPath('/edit/user/portfolio');
                            $addData('data-->editUrl',$urlProcessor->getUrlString());
                            $userData = $adminManager->get('user-portfolio-data',[
                                'userId' => $userId
                            ]);

                            $addData('data-->user-->portfolio',$userData);
                            $addData('data-->header-->title',"Scit View User Portfolio");
                            $addData('data-->for','view');
                            return $response->write($htmlManager->render('user/management/pages/profile/user/manager.html',$getData()));
                        break;

                        case 'general-edit-user-portfolio':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            if(!$userManager->hasPermissionAs($this->getSession()->get('userData-->type'))){
                                return $this->process('restricted-access',true);
                            }

                            $userId = $request->get('id');
                            if($userId){
                                if(!(($userId && ($userId = $adminManager->get('user-id-from-id',[
                                    'id' => $this->getUtils()->getDataOfHash($userId)
                                ]))))){
                                    $this->setPageNotification('error','Ooops invalid user.');
                                    return $response->redirect('/dashboard');
                                }

                                if(!in_array(true,[
                                    $userManager->hasPermissionAs('general-administrator'),
                                    $userManager->hasPermissionAs('support-administrator'),
                                    $userManager->hasPermissionAs('secondary-school-administrator')
                                ],true)){
                                    $this->setPageNotification('error','Ooops you cannot edit user account');
                                    return $response->redirect('/dashboard');
                                }
                            }else{
                                $userId = $this->getSession()->get('userData-->id');
                            }

                            $addData('data-->countries',$this->getUtils()->init('Users-Admin-Manager')->get('countries-list',[
                                'limit' => 20000
                            ]));

                            $addData('data-->settings-->region',[
                                'url' => '/get/regions',
                                'token' => $this->getTokenFor('get-regions')
                            ]);

                            $addData('data-->settings-->lga',[
                                'url' => '/get/lgas',
                                'token' => $this->getTokenFor('get-lgas')
                            ]);

                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $userData = $adminManager->get('user-portfolio-data',[
                                'userId' => $userId
                            ]);

                            $addData('data-->user-->portfolio',$userData);
                            $addData('data-->header-->title',"Scit Edit User Portfolio");
                            $addData('data-->for','edit');
                            return $response->write($htmlManager->render('user/management/pages/profile/user/manager.html',$getData()));
                        break;

                        case 'general-view-stakeholder-portfolio':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $userManager = $this->getUtils()->init('Users-Manager');

                            if(!in_array(true,[
                                $userManager->hasPermissionAs('general-administrator'),
                                $userManager->hasPermissionAs('support-administrator'),
                                $userManager->hasPermissionAs('secondary-school-administrator'),
                                $userManager->hasPermissionAs('internship-provider-administrator'),
                                $userManager->hasPermissionAs('institution-administrator')
                            ],true)){
                                return $this->process('restricted-access',true);
                            }

                            $id = $request->get('id');
                            $type = $request->get('type');

                            if($id && $type){
                                $id = $this->getUtils()->getDataOfHash($id);
                            }else{
                                if(!in_array(true,[
                                    $userManager->hasPermissionAs('secondary-school-administrator'),
                                    $userManager->hasPermissionAs('internship-provider-administrator'),
                                    $userManager->hasPermissionAs('institution-administrator')
                                ],true)){
                                    $this->setPageNotification('error','Ooops invalid stakeholder');
                                    return $response->redirect('/dashboard');
                                }

                                $userType = $this->getSession()->get('userData-->type');
                                $type = str_replace('-administrator','',$userType);
                                $id = $this->getSession()->get('adminData-->'.$userType.'-->stakeholderId');
                            }

                            if(!($id && in_array($type,['institution','secondary-school','internship-provider'],true))){
                                $this->setPageNotification('error','Ooops invalid stakeholder');
                                return $response->redirect('/dashboard');
                            }

                            if(in_array(true,[
                                $userManager->hasPermissionAs('general-administrator'),
                                $userManager->hasPermissionAs('support-administrator')
                            ],true)){
                                $urlProcessor = $this->getUtils()->init('General-UrlProcessor',[
                                    'url' => $request->getFullRequestUrl()
                                ]);

                                $urlProcessor->changePath($this->getSession()->get('userData-->type'))->addSubPath('/edit/stakeholder/portfolio');
                                $addData('data-->editUrl',$urlProcessor->getUrlString());
                            }

                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $stakeholderData = $adminManager->get('stakeholder-portfolio-data',[
                                'stakeholderId' => $id,
                                'stakeholderType' => $type
                            ]);

                            $addData('data-->stakeholder-->portfolio',$stakeholderData);
                            $addData('data-->header-->title',"Scit View Stakeholder Portfolio");
                            $addData('data-->for','view');
                            return $response->write($htmlManager->render('user/management/pages/profile/stakeholder/manager.html',$getData()));
                        break;

                        case 'general-edit-stakeholder-portfolio':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $userManager = $this->getUtils()->init('Users-Manager');

                            if(!in_array(true,[
                                $userManager->hasPermissionAs('general-administrator'),
                                $userManager->hasPermissionAs('support-administrator'),
                                $userManager->hasPermissionAs('secondary-school-administrator'),
                                $userManager->hasPermissionAs('internship-provider-administrator'),
                                $userManager->hasPermissionAs('institution-administrator')
                            ],true)){
                                return $this->process('restricted-access',true);
                            }

                            $id = $request->get('id');
                            $type = $request->get('type');
                            $userType = $this->getSession()->get('userData-->type');

                            if($id && $type){
                                $id = $this->getUtils()->getDataOfHash($id);
                            }else{
                                if(!in_array(true,[
                                    $userManager->hasPermissionAs('secondary-school-administrator'),
                                    $userManager->hasPermissionAs('internship-provider-administrator'),
                                    $userManager->hasPermissionAs('institution-administrator')
                                ],true)){
                                    $this->setPageNotification('error','Ooops invalid stakeholder');
                                    return $response->redirect('/dashboard');
                                }

                                $type = str_replace('-administrator','',$userType);
                                $id = $this->getSession()->get('adminData-->'.$userType.'-->stakeholderId');
                            }

                            if(!($id && in_array($type,['institution','secondary-school','internship-provider'],true))){
                                $this->setPageNotification('error','Ooops invalid stakeholder');
                                return $response->redirect('/dashboard');
                            }

                            $stakeholderData = $adminManager->get('stakeholder-portfolio-data',[
                                'stakeholderId' => $id,
                                'stakeholderType' => $type
                            ]);

                            $addData('data-->isUser',false);
                            if(!($stakeholderData['status'] == 'ok' && ((int) $stakeholderData['response']['id'] == $this->getSession()->get('adminData-->'.$userType.'-->stakeholderId')))){
                                $addData('data-->isUser',true);
                            }

                            $addData('data-->countries',$this->getUtils()->init('Users-Admin-Manager')->get('countries-list',[
                                'limit' => 20000
                            ]));

                            $addData('data-->settings-->region',[
                                'url' => '/get/regions',
                                'token' => $this->getTokenFor('get-regions')
                            ]);

                            $addData('data-->settings-->lga',[
                                'url' => '/get/lgas',
                                'token' => $this->getTokenFor('get-lgas')
                            ]);

                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $addData('data-->stakeholder-->portfolio',$stakeholderData);
                            $addData('data-->header-->title',"Scit Edit Stakeholder Portfolio");
                            $addData('data-->for','edit');
                            return $response->write($htmlManager->render('user/management/pages/profile/stakeholder/manager.html',$getData()));
                        break;

                        case 'general-add-user-portfolio':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            if(!in_array(true,[
                                $userManager->hasPermissionAs('general-administrator'),
                                $userManager->hasPermissionAs('support-administrator'),
                                $userManager->hasPermissionAs('secondary-school-administrator')
                            ])){
                                return $this->process('restricted-access',true);
                            }

                            $userTypes;
                            switch(true){
                                case ($this->getSession()->get('userData-->type') === 'secondary-school-administrator'):
                                    $userTypes = [
                                        'student'
                                    ];

                                    $requestData = [
                                        'from' => 0,
                                        'limit' => 1,
                                        'filters' => [
                                            [
                                                'pointer' => 'secondarySchoolId',
                                                'value' => $this->getSession()->get('adminData-->secondary-school-administrator-->stakeholderId'),
                                                'compareUsing' => 'equality'
                                            ]
                                        ]
                                    ];

                                    $stakeholder = $adminManager->get('secondary-school-list',$requestData);
                                    $addData('data-->select-->school',[
                                        'id' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderId'),
                                        'name' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderName'),
                                        'country' => [
                                            'id' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderCountryId'),
                                            'name' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderCountryName')
                                        ],
                                        'region' => [
                                            'id' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderRegionId'),
                                            'name' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderRegionName')
                                        ],
                                        'lga' => [
                                            'id' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderLgaId'),
                                            'name' => $this->getUtils()::getDataFromArray($stakeholder,'0-->stakeholderLgaName')
                                        ]
                                    ]);
                                break;
                                default:
                                    $userTypes = [
                                        'general-administrator',
                                        'support-administrator',
                                        'secondary-school-administrator',
                                        'internship-provider-administrator',
                                        'institution-administrator',
                                        'student'
                                    ];

                                    $addData('data-->select',[
                                        'url' => '/get/data',
                                        'token' => $this->getTokenFor('get-data')
                                    ]);
                                break;
                            }

                            $out = [];
                            foreach($userTypes as &$userType){
                                $out[] = [
                                    'id' => $userType,
                                    'name' => ucwords(str_replace('-',' ',$userType))
                                ];
                            }
                            $userTypes = &$out;
                            $addData('data-->userTypes',$userTypes);

                            $addData('data-->countries',$this->getUtils()->init('Users-Admin-Manager')->get('countries-list',[
                                'limit' => 20000
                            ]));

                            $addData('data-->settings-->region',[
                                'url' => '/get/regions',
                                'token' => $this->getTokenFor('get-regions')
                            ]);

                            $addData('data-->settings-->lga',[
                                'url' => '/get/lgas',
                                'token' => $this->getTokenFor('get-lgas')
                            ]);

                            $addData('data-->update-data',[
                                'url' => '/update-data',
                                'token' => $this->getTokenFor('update-data')
                            ]);

                            $addData('data-->header-->title',"Scit Add User Portfolio");
                            $addData('data-->for','add');
                            return $response->write($htmlManager->render('user/management/pages/profile/user/manager.html',$getData()));
                        break;
                    }
                }

                if($request->isPost()){
                    switch($page){
                        case 'get-data':
                            $for = $request->get('__for');
                            switch($for){
                                case 'stakeholderList':
                                    $usersAdminManager = $this->getUtils()->init('Users-Admin-Manager');
                                    $usersManager = $this->getUtils()->init('Users-Manager');

                                    if($usersManager->hasPermissionAs('general-administrator')){
                                        $queryKey = $request->get('query-->key');
                                        $queryValue = $request->get('query-->value');
                                        $filterData = function(&$data){
                                            $out = [];
                                            foreach($data as &$data){
                                                $out[] = [
                                                    'name' => &$data['stakeholderName'],
                                                    'id' => &$data['stakeholderId'],
                                                    'uniqueName' => &$data['stakeholderUniqueName']
                                                ];
                                            }

                                            return $out;
                                        };

                                        switch($queryKey){
                                            case 'stakeholders':
                                                switch($queryValue){
                                                    case 'institution-administrator':
                                                        $requestData = [
                                                            'from' => 0,
                                                            'limit' => 10000,
                                                            'filters' => [
                                                                [
                                                                    'pointer' => 'institutionCountryId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('countryId')),
                                                                    'compareUsing' => 'equality'
                                                                ],
                                                                [
                                                                    'pointer' => 'institutionRegionId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('regionId')),
                                                                    'compareUsing' => 'equality'
                                                                ],
                                                                [
                                                                    'pointer' => 'institutionLgaId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('lgaId')),
                                                                    'compareUsing' => 'equality'
                                                                ]
                                                            ]
                                                        ];

                                                        $stakeholders = $usersAdminManager->get('institutions-list',$requestData);
                                                        $data = $filterData($stakeholders);

                                                        $hasContent = (count($data) ? 1 : 0);
                                                        return $responder([
                                                            'status' => 'ok',
                                                            'hasData' => &$hasContent,
                                                            'response' => $data
                                                        ]);
                                                    break;

                                                    case 'internship-provider-administrator':
                                                        $requestData = [
                                                            'from' => 0,
                                                            'limit' => 10000,
                                                            'filters' => [
                                                                [
                                                                    'pointer' => 'internshipProviderCountryId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('countryId')),
                                                                    'compareUsing' => 'equality'
                                                                ],
                                                                [
                                                                    'pointer' => 'internshipProviderRegionId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('regionId')),
                                                                    'compareUsing' => 'equality'
                                                                ],
                                                                [
                                                                    'pointer' => 'internshipProviderLgaId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('lgaId')),
                                                                    'compareUsing' => 'equality'
                                                                ]
                                                            ]
                                                        ];

                                                        $stakeholders = $usersAdminManager->get('internship-providers-list',$requestData);
                                                        $data = $filterData($stakeholders);

                                                        $hasContent = (count($data) ? 1 : 0);
                                                        return $responder([
                                                            'status' => 'ok',
                                                            'hasData' => &$hasContent,
                                                            'response' => $data
                                                        ]);
                                                    break;

                                                    case 'secondary-school-administrator':
                                                        $requestData = [
                                                            'from' => 0,
                                                            'limit' => 10000,
                                                            'filters' => [
                                                                [
                                                                    'pointer' => 'secondarySchoolCountryId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('countryId')),
                                                                    'compareUsing' => 'equality'
                                                                ],
                                                                [
                                                                    'pointer' => 'secondarySchoolRegionId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('regionId')),
                                                                    'compareUsing' => 'equality'
                                                                ],
                                                                [
                                                                    'pointer' => 'secondarySchoolLgaId',
                                                                    'value' => $this->getUtils()->getDataOfHash($request->get('lgaId')),
                                                                    'compareUsing' => 'equality'
                                                                ]
                                                            ]
                                                        ];

                                                        $stakeholders = $usersAdminManager->get('secondary-schools-list',$requestData);
                                                        $data = $filterData($stakeholders);

                                                        $hasContent = (count($data) ? 1 : 0);
                                                        return $responder([
                                                            'status' => 'ok',
                                                            'hasData' => &$hasContent,
                                                            'response' => $data
                                                        ]);
                                                    break;
                                                }
                                            break;

                                            case 'schools':
                                                $requestData = [
                                                    'from' => 0,
                                                    'limit' => 10000,
                                                    'filters' => [
                                                        [
                                                            'pointer' => 'secondarySchoolCountryId',
                                                            'value' => $this->getUtils()->getDataOfHash($request->get('countryId')),
                                                            'compareUsing' => 'equality'
                                                        ],
                                                        [
                                                            'pointer' => 'secondarySchoolRegionId',
                                                            'value' => $this->getUtils()->getDataOfHash($request->get('regionId')),
                                                            'compareUsing' => 'equality'
                                                        ],
                                                        [
                                                            'pointer' => 'secondarySchoolLgaId',
                                                            'value' => $this->getUtils()->getDataOfHash($request->get('lgaId')),
                                                            'compareUsing' => 'equality'
                                                        ]
                                                    ]
                                                ];

                                                $stakeholders = $usersAdminManager->get('secondary-schools-list',$requestData);
                                                $data = $filterData($stakeholders);

                                                $hasContent = (count($data) ? 1 : 0);
                                                return $responder([
                                                    'status' => 'ok',
                                                    'hasData' => &$hasContent,
                                                    'response' => $data
                                                ]);
                                            break;
                                        }
                                    }
                                break;
                            }
                            return $responder(false,'Invalid request');
                        break;

                        case 'general-administrator-add-event':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $result = $adminManager->addEvent();
                            $outString = '';
                            $outType = 'error';

                            if($result['status'] == 'ok'){
                                if($result['response']['totalExists']){
                                    $prepend = ($outString ? ',' : '');
                                    $outString .= $prepend."{$result['response']['totalExists']} event already exists";
                                }

                                if($result['response']['totalFailed']){
                                    $prepend = ($outString ? ',' : '');
                                    $outString .= $prepend." {$result['response']['totalFailed']} event had errors";
                                }

                                if($result['response']['totalAdded']){
                                    $outType = 'success';
                                    $append = ($outString ? ', ' : '');
                                    $outString = "{$result['response']['totalAdded']} event was added succesfully".$append.$outString;
                                }
                            }else{
                                $outString = $result['response'];
                            }

                            $this->setPageNotification($outType,$outString);
                            return $responder(true,'');
                        break;

                        case 'general-administrator-view-event':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'update',
                                'remove'
                            ])[$action])){
                                return $this->getUtils()::getResponseFor('invalid-request');
                            }

                            switch($action){
                                case 'update':
                                    $result = $adminManager->updateEvent();
                                    if(is_array($result)){
                                        $status = $result['status'] == 'error' ?: 'success';
                                        $this->setPageNotification($status,$result['response']);
                                    }else{
                                        $this->setPageNotification('error','Invalid response');
                                    }


                                    echo var_dump($result);
                                    exit();
                                break;

                                case 'remove':
                                    $result = $adminManager->removeEvent();
                                    if(is_array($result)){
                                        $status = $result['status'] == 'error' ?: 'success';
                                        $this->setPageNotification($status,$result['response']);
                                    }else{
                                        $this->setPageNotification('error','Invalid response');
                                    }
                                break;
                            }

                            return $responder(true,'');
                        break;

                        case 'general-administrator-view-events':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $action = $request->get('action');

                            if(!isset(array_flip([
                                'remove'
                            ])[$action])){
                                return $this->getUtils()::getResponseFor('invalid-request');
                            }

                            switch($action){
                                case 'remove':
                                    $result = $adminManager->removeEvent();
                                    if(is_array($result)){
                                        $status = $result['status'] == 'error' ?: 'success';
                                        $this->setPageNotification($status,$result['response']);
                                    }else{
                                        $this->setPageNotification('error','Invalid response');
                                    }
                                break;
                            }

                            return $this->process($page,true);
                        break;

                        case 'student-take-test':
                            $studentManager = $this->getUtils()->init('Users-Student-Manager');
                            $type = $request->get('type');

                            if(!isset(array_flip([
                                'academic',
                                'temperament',
                                'bestSubjects'
                            ])[$type])){
                                $this->setPageNotification('error','Invalid request');
                                return $this->process($page,true);
                            }

                            switch($type){
                                case 'academic':
                                    $result = $studentManager->takeAcademicTest();
                                    if($result['status'] == 'ok'){
                                        $result = $result['response'];
                                        $this->set('studentTestResult',array_merge($result,[
                                            'type' => 'academic'
                                        ]));
                                    }else{
                                        $data = $this->getUtils()::getDataFromArray($result,'data');
                                        if(is_array($data)){
                                            $this->set('studentTestResult',array_merge($data,[
                                                'type' => 'academic'
                                            ]));
                                        }else{
                                            $this->setPageNotification('error',$result['response']);
                                        }
                                    }
                                break;

                                case 'temperament':
                                    $result = $studentManager->takeTemperamentTest();
                                    if($result['status'] == 'ok'){
                                        $result = $result['response'];
                                        $this->set('studentTestResult',array_merge($result,[
                                            'type' => 'temperament'
                                        ]));
                                    }else{
                                        $this->setPageNotification('error',$result['response']);
                                    }
                                break;

                                case 'bestSubjects':
                                    $result = $studentManager->takeBestSubjectsTest();
                                    if($result['status'] == 'ok'){
                                        $result = $result['response'];
                                        $this->set('studentTestResult',array_merge($result,[
                                            'type' => 'bestSubjects'
                                        ]));
                                    }else{
                                        $this->setPageNotification('error',$result['response']);
                                    }
                                break;
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-add-question':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $result = $adminManager->addQuestion();
                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                if($status === 'error'){
                                    $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                }
                                if($status === 'ok'){
                                    $invalidsDb = $this->getUtils()::getDataFromArray($result,'result-->invalids');
                                    $invalidsCount = count($invalidsDb);
                                    if($invalidsCount){
                                        $this->set('adminAddQuestion-->invalids',$invalidsDb);
                                    }

                                    $totalAdded = $this->getUtils()::getDataFromArray($result,'result-->totalQuestionsAdded') ?: [];
                                    $totalAdded = count($totalAdded);
                                    $getText = function($count,$text){
                                        if($count > 1){
                                            $text .= 's';
                                        }
                                        return "{$count} {$text}";
                                    };
                                    $pageNotification = '';
                                    if($totalAdded){
                                        $pageNotification = "Congratulations {$getText($totalAdded,'question')} was added succesfully";
                                        if($invalidsCount){
                                            $pageNotification .= ", {$getText($eexistsCount,'question')} has invalid inputs that are not supported";
                                        }
                                        $this->setPageNotification('success',$pageNotification);
                                    }else{
                                        $this->setPageNotification('error',"No question data was added");
                                    }
                                }
                            }else{
                                $this->setPageNotification('error','Ooops... error add questions data');
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-questions':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                $result = $adminManager->removeQuestions();
                                if(is_array($result)){
                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                    if($status === 'error'){
                                        $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                    }

                                    if($status === 'ok'){
                                        $text = '';
                                        $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                        $totalError = $this->getUtils()::getDataFromArray($result,'result-->totalError') ?: 0;

                                        $handleRemoved = function() use(&$totalRemoved){
                                            $text = '';
                                            if($totalRemoved){
                                                $isPlural = ($totalRemoved > 1);
                                                $text .= "{$totalRemoved} question".($isPlural ? 's' : '')." was removed";
                                            }else{
                                                $text .= 'No question was removed, ';
                                            }
                                            return $text;
                                        };

                                        $handleError = function() use(&$totalError){
                                            $text = '';
                                            if($totalError){
                                                $isPlural = ($totalError > 1);
                                                $text .= "{$totalError} question".($isPlural ? 's' : '')." had errors while removing";
                                            }
                                            return $text;
                                        };

                                        $text = $handleRemoved();
                                        $addedText = $handleError();
                                        if($addedText){
                                            $text .= ', '.$addedText;
                                        }

                                        $this->setPageNotification('success',$text);
                                    }
                                }else{
                                    $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-question':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove',
                                'update'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                switch($action){
                                    case 'remove':
                                        $result = $adminManager->removeQuestions();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                                if($totalRemoved){
                                                    $this->setPageNotification('success','Question removed succesfully');
                                                    return $response->redirect('/general-administrator/view/questions');
                                                }else{
                                                    $this->setPageNotification('error','An error occured while removing questions.. please try again later');
                                                }
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;

                                    case 'update':
                                        $result = $adminManager->updateTemperament();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $this->setPageNotification('success','Question Data updated succesfully');
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-approval-requests':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');

                            $result = $adminManager->updateAccountStatus([
                                'forId' => $request->get('forId'),
                                'for' => $request->get('for'),
                                'blockUser' => $request->get('blockUser'),
                                'status' => $request->get('action')
                            ]);

                            $status = $this->getUtils()::getDataFromArray($result,'status');
                            if($status === 'error'){
                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                            }else{
                                $this->setPageNotification('success',$this->getUtils()::getDataFromArray($result,'response'));
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-temperament':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove',
                                'update'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                switch($action){
                                    case 'remove':
                                        $result = $adminManager->removeTemperaments();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                                if($totalRemoved){
                                                    $this->setPageNotification('success','Temperament removed succesfully');
                                                    return $response->redirect('/general-administrator/view/temperaments');
                                                }else{
                                                    $this->setPageNotification('error','An error occured while removing temperaments.. please try again later');
                                                }
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;

                                    case 'update':
                                        $result = $adminManager->updateTemperament();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $this->setPageNotification('success','Temperament Data updated succesfully');
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-temperaments':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                $result = $adminManager->removeTemperaments();
                                if(is_array($result)){
                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                    if($status === 'error'){
                                        $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                    }

                                    if($status === 'ok'){
                                        $text = '';
                                        $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                        $totalError = $this->getUtils()::getDataFromArray($result,'result-->totalError') ?: 0;

                                        $handleRemoved = function() use(&$totalRemoved){
                                            $text = '';
                                            if($totalRemoved){
                                                $isPlural = ($totalRemoved > 1);
                                                $text .= "{$totalRemoved} temperament".($isPlural ? 's' : '')." was removed";
                                            }else{
                                                $text .= 'No temperament was removed, ';
                                            }
                                            return $text;
                                        };

                                        $handleError = function() use(&$totalError){
                                            $text = '';
                                            if($totalError){
                                                $isPlural = ($totalError > 1);
                                                $text .= "{$totalError} temperament".($isPlural ? 's' : '')." had errors while removing";
                                            }
                                            return $text;
                                        };

                                        $text = $handleRemoved();
                                        $addedText = $handleError();
                                        if($addedText){
                                            $text .= ', '.$addedText;
                                        }

                                        $this->setPageNotification('success',$text);
                                    }
                                }else{
                                    $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-add-temperament':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $result = $adminManager->addTemperament();
                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                if($status === 'error'){
                                    $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                }
                                if($status === 'ok'){
                                    $errorsDb = $this->getUtils()::getDataFromArray($result,'result-->errors');
                                    $errorsCount = count($errorsDb);
                                    if($errorsCount){
                                        $this->set('adminAddTemperament-->errors',$errorsDb);
                                    }
                                    $existsDb = $this->getUtils()::getDataFromArray($result,'result-->exists');
                                    $existsCount = count($existsDb);
                                    if($existsCount){
                                        $this->set('adminAddTemperament-->exists',$existsDb);
                                    }
                                    $invalidsDb = $this->getUtils()::getDataFromArray($result,'result-->invalids');
                                    $invalidsCount = count($invalidsDb);
                                    if($invalidsCount){
                                        $this->set('adminAddTemperament-->invalids',$invalidsDb);
                                    }
                                    $totalAdded = $this->getUtils()::getDataFromArray($result,'result-->added');
                                    $getText = function($count,$text){
                                        if($count > 1){
                                            $text .= 's';
                                        }
                                        return "{$count} {$text}";
                                    };
                                    $pageNotification = '';
                                    if($totalAdded){
                                        $pageNotification = "Congratulations {$getText($totalAdded,'temperament')} was added succesfully";
                                        if($errorsCount){
                                            $pageNotification .= ", {$getText($errorsCount,'temperament')} had errors while adding";
                                        }
                                        if($existsCount){
                                            $pageNotification .= ", {$getText($existsCount,'temperament')} already exists";
                                        }
                                        if($invalidsCount){
                                            $pageNotification .= ", {$getText($eexistsCount,'temperament')} has invalid inputs that are not supported";
                                        }
                                        $this->setPageNotification('success',$pageNotification);
                                    }else{
                                        $this->setPageNotification('error',"No temperament data was added");
                                    }
                                }
                            }else{
                                $this->setPageNotification('error','Ooops... error add temperaments data');
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-profession':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove',
                                'update'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                switch($action){
                                    case 'remove':
                                        $result = $adminManager->removeProfessions();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                                if($totalRemoved){
                                                    $this->setPageNotification('success','Profession removed succesfully');
                                                    return $response->redirect('/general-administrator/view/professions');
                                                }else{
                                                    $this->setPageNotification('error','An error occured while removing professions.. please try again later');
                                                }
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;

                                    case 'update':
                                        $result = $adminManager->updateProfession();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');

                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $this->setPageNotification('success','Profession Data updated succesfully');
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                    break;
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-professions':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                $result = $adminManager->removeProfessions();
                                if(is_array($result)){
                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                    if($status === 'error'){
                                        $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                    }

                                    if($status === 'ok'){
                                        $text = '';
                                        $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                        $totalError = $this->getUtils()::getDataFromArray($result,'result-->totalError') ?: 0;

                                        $handleRemoved = function() use(&$totalRemoved){
                                            $text = '';
                                            if($totalRemoved){
                                                $isPlural = ($totalRemoved > 1);
                                                $text .= "{$totalRemoved} profession".($isPlural ? 's' : '')." was removed";
                                            }else{
                                                $text .= 'No profession was removed, ';
                                            }
                                            return $text;
                                        };

                                        $handleError = function() use(&$totalError){
                                            $text = '';
                                            if($totalError){
                                                $isPlural = ($totalError > 1);
                                                $text .= "{$totalError} profession".($isPlural ? 's' : '')." had errors while removing";
                                            }
                                            return $text;
                                        };

                                        $text = $handleRemoved();
                                        $addedText = $handleError();
                                        if($addedText){
                                            $text .= ', '.$addedText;
                                        }

                                        $this->setPageNotification('success',$text);
                                    }
                                }else{
                                    $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-add-profession':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $result = $adminManager->addProfession();
                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                if($status === 'error'){
                                    $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                }
                                if($status === 'ok'){
                                    $errorsDb = $this->getUtils()::getDataFromArray($result,'result-->errors');
                                    $errorsCount = count($errorsDb);
                                    if($errorsCount){
                                        $this->set('adminAddProfession-->errors',$errorsDb);
                                    }
                                    $existsDb = $this->getUtils()::getDataFromArray($result,'result-->exists');
                                    $existsCount = count($existsDb);
                                    if($existsCount){
                                        $this->set('adminAddProfession-->exists',$existsDb);
                                    }
                                    $invalidsDb = $this->getUtils()::getDataFromArray($result,'result-->invalids');
                                    $invalidsCount = count($invalidsDb);
                                    if($invalidsCount){
                                        $this->set('adminAddProfession-->invalids',$invalidsDb);
                                    }
                                    $totalAdded = $this->getUtils()::getDataFromArray($result,'result-->added');
                                    $getText = function($count,$text){
                                        if($count > 1){
                                            $text .= 's';
                                        }
                                        return "{$count} {$text}";
                                    };
                                    $pageNotification = '';
                                    if($totalAdded){
                                        $pageNotification = "Congratulations {$getText($totalAdded,'profession')} was added succesfully";
                                        if($errorsCount){
                                            $pageNotification .= ", {$getText($errorsCount,'profession')} had errors while adding";
                                        }
                                        if($existsCount){
                                            $pageNotification .= ", {$getText($existsCount,'profession')} already exists";
                                        }
                                        if($invalidsCount){
                                            $pageNotification .= ", {$getText($eexistsCount,'profession')} has invalid inputs that are not supported";
                                        }
                                        $this->setPageNotification('success',$pageNotification);
                                    }else{
                                        $this->setPageNotification('error',"No profession data was added");
                                    }
                                }
                            }else{
                                $this->setPageNotification('error','Ooops... error add professions data');
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-discipline':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove',
                                'update'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                switch($action){
                                    case 'remove':
                                        $result = $adminManager->removeDisciplines();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                                if($totalRemoved){
                                                    $this->setPageNotification('success','Discipline removed succesfully');
                                                    return $response->redirect('/general-administrator/view/disciplines');
                                                }else{
                                                    $this->setPageNotification('error','An error occured while removing disciplines.. please try again later');
                                                }
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;

                                    case 'update':
                                        $result = $adminManager->updateDiscipline();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $this->setPageNotification('success','Discipline Data updated succesfully');
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                    break;
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-disciplines':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                $result = $adminManager->removeDisciplines();
                                if(is_array($result)){
                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                    if($status === 'error'){
                                        $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                    }

                                    if($status === 'ok'){
                                        $text = '';
                                        $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                        $totalError = $this->getUtils()::getDataFromArray($result,'result-->totalError') ?: 0;

                                        $handleRemoved = function() use(&$totalRemoved){
                                            $text = '';
                                            if($totalRemoved){
                                                $isPlural = ($totalRemoved > 1);
                                                $text .= "{$totalRemoved} discipline".($isPlural ? 's' : '')." was removed";
                                            }else{
                                                $text .= 'No discipline was removed, ';
                                            }
                                            return $text;
                                        };

                                        $handleError = function() use(&$totalError){
                                            $text = '';
                                            if($totalError){
                                                $isPlural = ($totalError > 1);
                                                $text .= "{$totalError} discipline".($isPlural ? 's' : '')." had errors while removing";
                                            }
                                            return $text;
                                        };

                                        $text = $handleRemoved();
                                        $addedText = $handleError();
                                        if($addedText){
                                            $text .= ', '.$addedText;
                                        }

                                        $this->setPageNotification('success',$text);
                                    }
                                }else{
                                    $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-add-discipline':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $result = $adminManager->addDiscipline();
                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                if($status === 'error'){
                                    $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                }
                                if($status === 'ok'){
                                    $errorsDb = $this->getUtils()::getDataFromArray($result,'result-->errors');
                                    $errorsCount = count($errorsDb);
                                    if($errorsCount){
                                        $this->set('adminAddDiscipline-->errors',$errorsDb);
                                    }
                                    $existsDb = $this->getUtils()::getDataFromArray($result,'result-->exists');
                                    $existsCount = count($existsDb);
                                    if($existsCount){
                                        $this->set('adminAddDiscipline-->exists',$existsDb);
                                    }
                                    $invalidsDb = $this->getUtils()::getDataFromArray($result,'result-->invalids');
                                    $invalidsCount = count($invalidsDb);
                                    if($invalidsCount){
                                        $this->set('adminAddDiscipline-->invalids',$invalidsDb);
                                    }
                                    $totalAdded = $this->getUtils()::getDataFromArray($result,'result-->added');
                                    $getText = function($count,$text){
                                        if($count > 1){
                                            $text .= 's';
                                        }
                                        return "{$count} {$text}";
                                    };
                                    $pageNotification = '';
                                    if($totalAdded){
                                        $pageNotification = "Congratulations {$getText($totalAdded,'discipline')} was added succesfully";
                                        if($errorsCount){
                                            $pageNotification .= ", {$getText($errorsCount,'discipline')} had errors while adding";
                                        }
                                        if($existsCount){
                                            $pageNotification .= ", {$getText($existsCount,'discipline')} already exists";
                                        }
                                        if($invalidsCount){
                                            $pageNotification .= ", {$getText($eexistsCount,'discipline')} has invalid inputs that are not supported";
                                        }
                                        $this->setPageNotification('success',$pageNotification);
                                    }else{
                                        $this->setPageNotification('error',"No discipline data was added");
                                    }
                                }
                            }else{
                                $this->setPageNotification('error','Ooops... error add disciplines data');
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-subject':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove',
                                'update'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                switch($action){
                                    case 'remove':
                                        $result = $adminManager->removeSubjects();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                                if($totalRemoved){
                                                    $this->setPageNotification('success','Subject removed succesfully');
                                                    return $response->redirect('/general-administrator/view/subjects');
                                                }else{
                                                    $this->setPageNotification('error','An error occured while removing subjects.. please try again later');
                                                }
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;

                                    case 'update':
                                        $result = $adminManager->updateSubject();
                                        if(is_array($result)){
                                            $status = $this->getUtils()::getDataFromArray($result,'status');
                                            if($status === 'error'){
                                                $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            }

                                            if($status === 'ok'){
                                                $this->setPageNotification('success','Subject Data updated succesfully');
                                            }
                                        }else{
                                            $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                        }
                                        return $this->process($page,true);
                                    break;
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-view-subjects':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $action = $request->get('action');
                            if(!isset(array_flip([
                                'remove'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid request');
                            }else{
                                $result = $adminManager->removeSubjects();
                                if(is_array($result)){
                                    $status = $this->getUtils()::getDataFromArray($result,'status');
                                    if($status === 'error'){
                                        $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                    }

                                    if($status === 'ok'){
                                        $text = '';
                                        $totalRemoved = $this->getUtils()::getDataFromArray($result,'result-->totalRemoved') ?: 0;
                                        $totalError = $this->getUtils()::getDataFromArray($result,'result-->totalError') ?: 0;

                                        $handleRemoved = function() use(&$totalRemoved){
                                            $text = '';
                                            if($totalRemoved){
                                                $isPlural = ($totalRemoved > 1);
                                                $text .= "{$totalRemoved} subject".($isPlural ? 's' : '')." was removed";
                                            }else{
                                                $text .= 'No subject was removed, ';
                                            }
                                            return $text;
                                        };

                                        $handleError = function() use(&$totalError){
                                            $text = '';
                                            if($totalError){
                                                $isPlural = ($totalError > 1);
                                                $text .= "{$totalError} subject".($isPlural ? 's' : '')." had errors while removing";
                                            }
                                            return $text;
                                        };

                                        $text = $handleRemoved();
                                        $addedText = $handleError();
                                        if($addedText){
                                            $text .= ', '.$addedText;
                                        }

                                        $this->setPageNotification('success',$text);
                                    }
                                }else{
                                    $this->setPageNotification('error','Ooops... an error occured while modifying data');
                                }
                            }
                            return $this->process($page,true);
                        break;

                        case 'general-administrator-add-subject':
                            $adminManager = $this->getUtils()->init('Users-Admin-Manager');
                            $result = $adminManager->addSubject();
                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                if($status === 'error'){
                                    $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                }
                                if($status === 'ok'){
                                    $errorsDb = $this->getUtils()::getDataFromArray($result,'result-->errors');
                                    $errorsCount = count($errorsDb);
                                    if($errorsCount){
                                        $this->set('adminAddSubject-->errors',$errorsDb);
                                    }
                                    $existsDb = $this->getUtils()::getDataFromArray($result,'result-->exists');
                                    $existsCount = count($existsDb);
                                    if($existsCount){
                                        $this->set('adminAddSubject-->exists',$existsDb);
                                    }
                                    $invalidsDb = $this->getUtils()::getDataFromArray($result,'result-->invalids');
                                    $invalidsCount = count($invalidsDb);
                                    if($invalidsCount){
                                        $this->set('adminAddSubject-->invalids',$invalidsDb);
                                    }
                                    $totalAdded = $this->getUtils()::getDataFromArray($result,'result-->added');
                                    $getText = function($count,$text){
                                        if($count > 1){
                                            $text .= 's';
                                        }
                                        return "{$count} {$text}";
                                    };
                                    $pageNotification = '';
                                    if($totalAdded){
                                        $pageNotification = "Congratulations {$getText($totalAdded,'subject')} was added succesfully";
                                        if($errorsCount){
                                            $pageNotification .= ", {$getText($errorsCount,'subject')} had errors while adding";
                                        }
                                        if($existsCount){
                                            $pageNotification .= ", {$getText($existsCount,'subject')} already exists";
                                        }
                                        if($invalidsCount){
                                            $pageNotification .= ", {$getText($eexistsCount,'subject')} has invalid inputs that are not supported";
                                        }
                                        $this->setPageNotification('success',$pageNotification);
                                    }else{
                                        $this->setPageNotification('error',"No subject data was added");
                                    }
                                }
                            }else{
                                $this->setPageNotification('error','Ooops... error add subjects data');
                            }
                            return $this->process($page,true);
                        break;

                        case 'user-logout':
                            $userManager->logout();
                            $this->setPageNotification('success','User logged out succesfully');
                            return $response->redirect($this->getContinueUrlAfter('user-logout'));
                        break;

                        case 'user-account-verification':
                            $action = $request->get('action');
                            if(isset(array_flip([
                                'verify',
                                'resend'
                            ])[$action])){
                                $this->setPageNotification('error','Ooops invalid email validation request');
                            }

                            switch($action){
                                case 'verify':
                                    $result = $userManager->processEmailVerification();
                                    if(is_array($result)){
                                        $status = $this->getUtils()::getDataFromArray($result,'status');
                                        if($status === 'error'){
                                            $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                            return $this->process($page,true);
                                        }
                                        if($status === 'ok'){
                                            $this->setPageNotification('success','Congratulations your account has been verified');
                                            return $response->redirect($this->getContinueUrlAfter($page));
                                        }
                                    }
                                break;

                                case 'resend':
                                    $result = $userManager->sendEmailVerificationData();
                                    if(is_array($result)){
                                        $status = $this->getUtils()::getDataFromArray($result,'status');
                                        if($status === 'error'){
                                            $this->setPageNotification('error',$this->getUtils()::getDataFromArray($result,'response'));
                                        }
                                        if($status === 'ok'){
                                            $this->setPageNotification('success','Email sent successfully');
                                        }
                                    }else{
                                        $this->setPageNotification('error','Ooops... error sending verification mail');
                                    }
                                    return $this->process($page,true);
                                break;
                            }
                        break;

                        case 'user-complete-registration':
                            $result = $this->getUserManager()->addStakeholderData();
                            if(is_array($result)){
                                $status = $this->getUtils()::getDataFromArray($result,'status');
                                if($status === 'error'){
                                    $from = $this->getUtils()::getDataFromArray($result,'from');
                                    $resp = $this->getUtils()::getDataFromArray($result,'response');
                                    if(($from === 'general') && is_string($resp)){
                                        $this->setPageNotification('error',$resp);
                                    }else{
                                        $this->set('errorResult',$result);
                                    }
                                }
                                if($status === 'ok'){
                                    $this->setPageNotification('success','Congratulations your registration is now complete');
                                    return $response->redirect($this->getContinueUrlAfter($page));
                                }
                            }else{
                                $this->setPageNotification('error','Ooops... error completing registration');
                            }
                            return $this->process($page,true);
                        break;
                    }
                }
            }
        }

        return $response->redirect('/404');
    }
}