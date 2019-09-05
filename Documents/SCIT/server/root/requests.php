<?php
$request = $utils->init('General-Request');
$response = $utils->init('General-Response');
$processor = $utils->init('General-Processor');

switch(true){
    case $request->for('')->only():
        return $processor->process('home');
    break;

    case $request->for('about-us')->only():
        return $processor->process('about-us');
    break;

    case $request->for('services')->only():
        return $processor->process('services');
    break;

    case $request->for('blog')->all():
        return $processor->process('blog');
    break;

    case $request->for('login')->all():
        return $response->redirect('/user/login',301);
    break;

    case $request->for('register')->all():
        return $response->redirect('/user/register',301);
    break;

    case $request->for('forgot-password')->all():
        return $response->redirect('/user/forgot-passsword',301);
    break;

    case $request->for('event')->only():
        return $processor->process('event');
    break;

    case $request->for('events')->only():
        return $processor->process('events');
    break;

    case $request->for('discipline')->only():
        return $processor->process('discipline');
    break;

    case $request->for('disciplines')->only():
        return $processor->process('disciplines');
    break;

    case $request->for('institution')->only():
        return $processor->process('institution');
    break;

    case $request->for('institutions')->only():
        return $processor->process('institutions');
    break;

    case $request->for('404')->only():
        return $processor->process('404');
    break;

    case $request->for('dashboard')->all():
        $requestUrl = $request->getFullRequestUrl();
        $urlProcessor = $utils->init('General-UrlProcessor',[
            'url' => $requestUrl
        ]);
        $oldPath = $urlProcessor->getPath();
        $newUrl = $urlProcessor->changePath('/user')->addSubPath($oldPath)->getUrlString();
        return $response->redirect($newUrl,301);
    break;

    case $request->for('restricted-access')->only():
        return $processor->process('restricted-access');
    break;

    case $request->for('user')->all():
        $userRequest = $request->for('user')->processChild();

        switch(true){
            case $userRequest->for('logout')->only():
                return $processor->process('user-logout');
            break;

            case $userRequest->for('register')->only():
                return $processor->process('user-register');
            break;

            case $userRequest->for('login')->only():
                return $processor->process('user-login');
            break;

            case $userRequest->for('forgot-password')->only():
                return $processor->process('user-forgot-password');
            break;

            case $userRequest->for('account')->all():
                $accountUserRequest = $userRequest->for('account')->processChild();

                switch(true){
                    case $accountUserRequest->for('verification')->only():
                        return $processor->process('user-account-verification');
                    break;

                    case $accountUserRequest->for('approval')->only():
                        return $processor->process('user-account-approval');
                    break;
                }

            break;

            case $userRequest->for('complete')->all():
                $completeUserRequest = $userRequest->for('complete')->processChild();

                switch(true){
                    case $completeUserRequest->for('registration')->only():
                        return $processor->process('user-complete-registration');
                    break;
                }
            break;

            case $userRequest->for('dashboard')->all():
                $dashboardRequest = $request->for('dashboard')->processChild();

                switch(true){
                    case $dashboardRequest->only():
                        return $processor->process('user-dashboard-home');
                    break;

                    case $dashboardRequest->for('logout')->only():
                        return $processor->process('user-dashboard-logout');
                    break;
                }
            break;
        }

    break;

    case $request->for('general-administrator')->all():
        $generalAdminRequest = $request->for('general-administrator')->processChild();

        switch(true){
            case ($generalAdminRequest->only() || $generalAdminRequest->for('dashboard')->only()):
                return $processor->process('general-administrator-dashboard-home');
            break;

            case ($generalAdminRequest->for('messenger')->only()):
                return $processor->process('general-administrator-messenger');
            break;

            case ($generalAdminRequest->for('ticket')->only()):
                return $processor->process('general-ticket');
            break;

            case $generalAdminRequest->for('edit')->all():
                $generalAdminEditRequest = $generalAdminRequest->for('edit')->processChild();
                switch(true){
                    case $generalAdminEditRequest->for('user')->all():
                        $generalAdminEditUserRequest = $generalAdminEditRequest->for('user')->processChild();

                        switch(true){
                            case $generalAdminEditUserRequest->for('portfolio')->only():
                                return $processor->process('general-edit-user-portfolio');
                            break;
                        }
                    break;

                    case $generalAdminEditRequest->for('stakeholder')->all():
                        $generalAdminEditStakeholderRequest = $generalAdminEditRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $generalAdminEditStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-edit-stakeholder-portfolio');
                            break;
                        }
                    break;
                }
            break;

            case $generalAdminRequest->for('view')->all():
                $generalAdminViewRequest = $generalAdminRequest->for('view')->processChild();
                switch(true){
                    case $generalAdminViewRequest->for('user')->all():
                        $generalAdminViewUserRequest = $generalAdminViewRequest->for('user')->processChild();

                        switch(true){
                            case $generalAdminViewUserRequest->for('portfolio')->only():
                                return $processor->process('general-view-user-portfolio');
                            break;
                        }
                    break;

                    case $generalAdminViewRequest->for('stakeholder')->all():
                        $generalAdminViewStakeholderRequest = $generalAdminViewRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $generalAdminViewStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-view-stakeholder-portfolio');
                            break;
                        }
                    break;

                    case $generalAdminViewRequest->for('subjects')->only():
                        return $processor->process('general-administrator-view-subjects');
                    break;

                    case $generalAdminViewRequest->for('subject')->only():
                        return $processor->process('general-administrator-view-subject');
                    break;

                    case $generalAdminViewRequest->for('disciplines')->only():
                        return $processor->process('general-administrator-view-disciplines');
                    break;

                    case $generalAdminViewRequest->for('discipline')->only():
                        return $processor->process('general-administrator-view-discipline');
                    break;

                    case $generalAdminViewRequest->for('professions')->only():
                        return $processor->process('general-administrator-view-professions');
                    break;

                    case $generalAdminViewRequest->for('profession')->only():
                        return $processor->process('general-administrator-view-profession');
                    break;

                    case $generalAdminViewRequest->for('temperaments')->only():
                        return $processor->process('general-administrator-view-temperaments');
                    break;

                    case $generalAdminViewRequest->for('temperament')->only():
                        return $processor->process('general-administrator-view-temperament');
                    break;

                    case $generalAdminViewRequest->for('approval-requests')->only():
                        return $processor->process('general-administrator-view-approval-requests');
                    break;

                    case $generalAdminViewRequest->for('questions')->only():
                        return $processor->process('general-administrator-view-questions');
                    break;

                    case $generalAdminViewRequest->for('question')->only():
                        return $processor->process('general-administrator-view-question');
                    break;

                    case $generalAdminViewRequest->for('general-administrators')->only():
                        return $processor->process('general-administrator-view-general-administrators');
                    break;

                    case $generalAdminViewRequest->for('support-administrators')->only():
                        return $processor->process('general-administrator-view-support-administrators');
                    break;

                    case $generalAdminViewRequest->for('internship-provider-administrators')->only():
                        return $processor->process('general-administrator-view-internship-provider-administrators');
                    break;

                    case $generalAdminViewRequest->for('institution-administrators')->only():
                        return $processor->process('general-administrator-view-institution-administrators');
                    break;

                    case $generalAdminViewRequest->for('secondary-school-administrators')->only():
                        return $processor->process('general-administrator-view-secondary-school-administrators');
                    break;

                    case $generalAdminViewRequest->for('students')->only():
                        return $processor->process('general-administrator-view-students');
                    break;

                    case $generalAdminViewRequest->for('internship-providers')->only():
                        return $processor->process('general-administrator-view-internship-providers');
                    break;

                    case $generalAdminViewRequest->for('institutions')->only():
                        return $processor->process('general-administrator-view-institutions');
                    break;

                    case $generalAdminViewRequest->for('secondary-schools')->only():
                        return $processor->process('general-administrator-view-secondary-schools');
                    break;

                    case $generalAdminViewRequest->for('event')->only():
                        return $processor->process('general-administrator-view-event');
                    break;

                    case $generalAdminViewRequest->for('events')->only():
                        return $processor->process('general-administrator-view-events');
                    break;

                    case $generalAdminViewRequest->for('faq')->only():
                        return $processor->process('general-view-faq');
                    break;

                    case $generalAdminViewRequest->for('faqs')->only():
                        return $processor->process('general-view-faqs');
                    break;

                    case $generalAdminViewRequest->for('support')->all():
                        $generalAdminViewSupportRequest = $generalAdminViewRequest->for('support')->processChild();

                        switch(true){
                            case $generalAdminViewSupportRequest->for('tickets')->only():
                                return $processor->process('general-view-support-tickets');
                            break;

                            case $generalAdminViewSupportRequest->for('ticket')->only():
                                return $processor->process('general-view-support-ticket');
                            break;
                        }
                    break;
                }
            break;

            case $generalAdminRequest->for('add')->all():
                $generalAdminAddRequest = $generalAdminRequest->for('add')->processChild();

                switch(true){
                    case $generalAdminAddRequest->for('subject')->only():
                        return $processor->process('general-administrator-add-subject');
                    break;

                    case $generalAdminAddRequest->for('discipline')->only():
                        return $processor->process('general-administrator-add-discipline');
                    break;

                    case $generalAdminAddRequest->for('profession')->only():
                        return $processor->process('general-administrator-add-profession');
                    break;

                    case $generalAdminAddRequest->for('temperament')->only():
                        return $processor->process('general-administrator-add-temperament');
                    break;

                    case $generalAdminAddRequest->for('question')->only():
                        return $processor->process('general-administrator-add-question');
                    break;

                    case $generalAdminAddRequest->for('event')->only():
                        return $processor->process('general-administrator-add-event');
                    break;

                    case $generalAdminAddRequest->for('faq')->only():
                        return $processor->process('general-add-faq');
                    break;

                    case $generalAdminAddRequest->for('support')->all():
                        $generalAdminAddSupportRequest = $generalAdminAddRequest->for('support')->processChild();

                        switch(true){
                            case $generalAdminAddSupportRequest->for('ticket'):
                                return $processor->process('general-add-support-ticket');
                            break;
                        }
                    break;

                    case $generalAdminAddRequest->for('user')->all():
                        $generalAdminAddUserRequest = $generalAdminAddRequest->for('user')->processChild();

                        switch(true){
                            case $generalAdminAddUserRequest->for('portfolio')->only():
                                return $processor->process('general-add-user-portfolio');
                            break;
                        }
                    break;

                    case $generalAdminAddRequest->for('stakeholder')->all():
                        $generalAdminAddStakeholderRequest = $generalAdminAddRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $generalAdminAddStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-add-stakeholder-portfolio');
                            break;
                        }
                    break;
                }
            break;
        }
    break;

    case $request->for('support-administrator')->all():
        $supportAdminRequest = $request->for('support-administrator')->processChild();

        switch(true){
            case ($supportAdminRequest->only() || $supportAdminRequest->for('dashboard')->only()):
                return $processor->process('support-administrator-dashboard-home');
            break;

            case ($supportAdminRequest->for('messenger')->only()):
                return $processor->process('support-administrator-messenger');
            break;

            case $supportAdminRequest->for('edit')->all():
                $supportAdminEditRequest = $supportAdminRequest->for('edit')->processChild();
                switch(true){
                    case $supportAdminEditRequest->for('user')->all():
                        $supportAdminEditUserRequest = $supportAdminEditRequest->for('user')->processChild();

                        switch(true){
                            case $supportAdminEditUserRequest->for('portfolio')->only():
                                return $processor->process('general-edit-user-portfolio');
                            break;
                        }
                    break;

                    case $supportAdminEditRequest->for('stakeholder')->all():
                        $supportAdminEditStakeholderRequest = $supportAdminEditRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $supportAdminEditStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-edit-stakeholder-portfolio');
                            break;
                        }
                    break;
                }
            break;

            case $supportAdminRequest->for('view')->all():
                $supportAdminViewRequest = $supportAdminRequest->for('view')->processChild();
                switch(true){
                    case $supportAdminViewRequest->for('user')->all():
                        $supportAdminViewUserRequest = $supportAdminViewRequest->for('user')->processChild();

                        switch(true){
                            case $supportAdminViewUserRequest->for('portfolio')->only():
                                return $processor->process('general-view-user-portfolio');
                            break;
                        }
                    break;

                    case $supportAdminViewRequest->for('stakeholder')->all():
                        $supportAdminViewStakeholderRequest = $supportAdminViewRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $supportAdminViewStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-view-stakeholder-portfolio');
                            break;
                        }
                    break;

                    case $supportAdminViewRequest->for('students')->only():
                        return $processor->process('support-administrator-view-students');
                    break;

                    case $supportAdminViewRequest->for('internship-providers')->only():
                        return $processor->process('support-administrator-view-internship-providers');
                    break;

                    case $supportAdminViewRequest->for('institutions')->only():
                        return $processor->process('support-administrator-view-institutions');
                    break;

                    case $supportAdminViewRequest->for('secondary-schools')->only():
                        return $processor->process('general-administrator-view-secondary-schools');
                    break;

                    case $supportAdminViewRequest->for('faq')->only():
                        return $processor->process('general-view-faq');
                    break;

                    case $supportAdminViewRequest->for('faqs')->only():
                        return $processor->process('general-view-faqs');
                    break;

                    case $supportAdminViewRequest->for('support')->all():
                        $supportAdminViewSupportRequest = $supportAdminViewRequest->for('support')->processChild();

                        switch(true){
                            case $supportAdminViewSupportRequest->for('ticket')->only():
                                return $processor->process('general-view-support-ticket');
                            break;

                            case $supportAdminViewSupportRequest->for('tickets')->only():
                                return $processor->process('general-view-support-tickets');
                            break;
                        }
                    break;
                }
            break;

            case $supportAdminRequest->for('add')->all():
                $supportAdminAddRequest = $supportAdminRequest->for('add')->processChild();

                switch(true){
                    case $supportAdminAddRequest->for('faq')->only():
                        return $processor->process('general-add-faq');
                    break;

                    case $supportAdminAddRequest->for('support')->all():
                        $supportAdminAddSupportRequest = $supportAdminAddRequest->for('support')->processChild();

                        switch(true){
                            case $supportAdminAddSupportRequest->for('ticket'):
                                return $processor->process('general-add-support-ticket');
                            break;
                        }
                    break;

                    case $supportAdminAddRequest->for('user')->all():
                        $supportAdminAddUserRequest = $supportAdminAddRequest->for('user')->processChild();

                        switch(true){
                            case $supportAdminAddSupportRequest->for('portfolio')->only():
                                return $processor->process('general-add-user-portfolio');
                            break;
                        }
                    break;

                    case $supportAdminAddRequest->for('stakeholder')->all():
                        $supportAdminAddStakeholderRequest = $supportAdminAddRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $supportAdminAddStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-add-stakeholder-portfolio');
                            break;
                        }
                    break;
                }
            break;
        }
    break;

    case $request->for('secondary-school-administrator')->all():
        $secondarySchoolAdminRequest = $request->for('secondary-school-administrator')->processChild();

        switch(true){
            case ($secondarySchoolAdminRequest->only() || $secondarySchoolAdminRequest->for('dashboard')->only()):
                return $processor->process('secondary-school-administrator-dashboard-home');
            break;

            case ($secondarySchoolAdminRequest->for('messenger')->only()):
                return $processor->process('secondary-school-administrator-messenger');
            break;

            case $secondarySchoolAdminRequest->for('edit')->all():
                $secondarySchoolAdminEditRequest = $secondarySchoolAdminRequest->for('edit')->processChild();
                switch(true){
                    case $secondarySchoolAdminEditRequest->for('user')->all():
                        $secondarySchoolAdminEditUserRequest = $secondarySchoolAdminEditRequest->for('user')->processChild();

                        switch(true){
                            case $secondarySchoolAdminEditUserRequest->for('portfolio')->only():
                                return $processor->process('general-edit-user-portfolio');
                            break;
                        }
                    break;

                    case $secondarySchoolAdminEditRequest->for('stakeholder')->all():
                        $secondarySchoolAdminEditStakeholderRequest = $secondarySchoolAdminEditRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $secondarySchoolAdminEditStakholderRequest->for('portfolio')->only():
                                return $processor->process('general-edit-stakeholder-portfolio');
                            break;
                        }
                    break;
                }
            break;

            case $secondarySchoolAdminRequest->for('view')->all():
                $secondarySchoolAdminViewRequest = $secondarySchoolAdminRequest->for('view')->processChild();
                switch(true){
                    case $secondarySchoolAdminViewRequest->for('user')->all():
                        $secondarySchoolAdminViewUserRequest = $secondarySchoolAdminViewRequest->for('user')->processChild();

                        switch(true){
                            case $secondarySchoolAdminViewUserRequest->for('portfolio')->only():
                                return $processor->process('general-view-user-portfolio');
                            break;
                        }
                    break;

                    case $secondarySchoolAdminViewRequest->for('stakeholder')->all():
                        $secondarySchoolAdminViewStakeholderRequest = $secondarySchoolAdminViewRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $secondarySchoolAdminViewStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-view-stakeholder-portfolio');
                            break;
                        }
                    break;

                    case $secondarySchoolAdminViewRequest->for('students')->only():
                        return $processor->process('secondary-school-administrator-view-students');
                    break;

                    case $secondarySchoolAdminViewRequest->for('applicants')->only():
                        return $processor->process('secondary-school-administrator-view-applicants');
                    break;

                    case $secondarySchoolAdminViewRequest->for('support')->all():
                        $secondarySchoolAdminViewSupportRequest = $secondarySchoolAdminViewRequest->for('support')->processChild();

                        switch(true){
                            case $secondarySchoolAdminViewSupportRequest->for('ticket')->only():
                                return $processor->process('general-view-support-ticket');
                            break;

                            case $secondarySchoolAdminViewSupportRequest->for('tickets')->only():
                                return $processor->process('general-view-support-tickets');
                            break;
                        }
                    break;
                }
            break;

            case $secondarySchoolAdminRequest->for('add')->all():
                $secondarySchoolAdminAddRequest = $secondarySchoolAdminRequest->for('add')->processChild();

                switch(true){
                    case $secondarySchoolAdminAddRequest->for('support')->all():
                        $secondarySchoolAdminAddSupportRequest = $secondarySchoolAdminAddRequest->for('support')->processChild();

                        switch(true){
                            case $secondarySchoolAdminAddSupportRequest->for('ticket'):
                                return $processor->process('general-add-support-ticket');
                            break;
                        }
                    break;

                    case $secondarySchoolAdminAddRequest->for('user')->all():
                        $secondarySchoolAdminAddUserRequest = $secondarySchoolAdminAddRequest->for('user')->processChild();

                        switch(true){
                            case $secondarySchoolAdminAddUserRequest->for('portfolio')->only():
                                return $processor->process('general-add-user-portfolio');
                            break;
                        }
                    break;
                }
            break;
        }
    break;

    case $request->for('internship-provider-administrator')->all():
        $internshipProviderAdminRequest = $request->for('internship-provider-administrator')->processChild();

        switch(true){
            case ($internshipProviderAdminRequest->only() || $internshipProviderAdminRequest->for('dashboard')->only()):
                return $processor->process('internship-provider-administrator-dashboard-home');
            break;

            case ($internshipProviderAdminRequest->for('messenger')->only()):
                return $processor->process('secondary-school-administrator-messenger');
            break;

            case $internshipProviderAdminRequest->for('edit')->all():
                $internshipProviderAdminEditRequest = $internshipProviderAdminRequest->for('edit')->processChild();
                switch(true){
                    case $internshipProviderAdminEditRequest->for('user')->all():
                        $internshipProviderAdminEditUserRequest = $internshipProviderAdminEditRequest->for('user')->processChild();

                        switch(true){
                            case $internshipProviderAdminEditUserRequest->for('portfolio')->only():
                                return $processor->process('general-edit-user-portfolio');
                            break;
                        }
                    break;

                    case $internshipProviderAdminEditRequest->for('stakeholder')->all():
                        $internshipProviderAdminEditStakeholderRequest = $internshipProviderAdminEditRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $internshipProviderAdminEditStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-edit-stakeholder-portfolio');
                            break;
                        }
                    break;
                }
            break;

            case $internshipProviderAdminRequest->for('view')->all():
                $internshipProviderAdminViewRequest = $internshipProviderAdminRequest->for('view')->processChild();
                switch(true){
                    case $internshipProviderAdminViewRequest->for('user')->all():
                        $internshipProviderAdminViewUserRequest = $internshipProviderAdminViewRequest->for('user')->processChild();

                        switch(true){
                            case $internshipProviderAdminViewUserRequest->for('portfolio')->only():
                                return $processor->process('general-view-user-portfolio');
                            break;
                        }
                    break;

                    case $internshipProviderAdminViewRequest->for('stakeholder')->all():
                        $internshipProviderAdminViewStakeholderRequest = $internshipProviderAdminViewRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $internshipProviderAdminViewStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-view-stakeholder-portfolio');
                            break;
                        }
                    break;

                    case $internshipProviderAdminViewRequest->for('students')->only():
                        return $processor->process('internship-provider-administrator-view-students');
                    break;

                    case $internshipProviderAdminViewRequest->for('applicants')->only():
                        return $processor->process('internship-provider-administrator-view-applicants');
                    break;

                    case $internshipProviderAdminViewRequest->for('support')->all():
                        $internshipProviderAdminViewSupportRequest = $internshipProviderAdminViewRequest->for('support')->processChild();

                        switch(true){
                            case $internshipProviderAdminViewSupportRequest->for('ticket')->only():
                                return $processor->process('general-view-support-ticket');
                            break;

                            case $internshipProviderAdminViewSupportRequest->for('tickets')->only():
                                return $processor->process('general-view-support-tickets');
                            break;
                        }
                    break;
                }
            break;

            case $internshipProviderAdminRequest->for('add')->all():
                $internshipProviderAdminAddRequest = $internshipProviderAdminRequest->for('add')->processChild();

                switch(true){
                    case $internshipProviderAdminAddRequest->for('support')->all():
                        $internshipProviderAdminAddSupportRequest = $internshipProviderAdminAddRequest->for('support')->processChild();

                        switch(true){
                            case $internshipProviderAdminAddSupportRequest->for('ticket'):
                                return $processor->process('general-add-support-ticket');
                            break;
                        }
                    break;
                }
            break;
        }
    break;

    case $request->for('institution-administrator')->all():
        $institutionAdminRequest = $request->for('institution-administrator')->processChild();

        switch(true){
            case ($institutionAdminRequest->only() || $institutionAdminRequest->for('dashboard')->only()):
                return $processor->process('institution-administrator-dashboard-home');
            break;

            case $institutionAdminRequest->for('edit')->all():
                $institutionAdminEditRequest = $institutionAdminRequest->for('edit')->processChild();
                switch(true){
                    case $institutionAdminEditRequest->for('user')->all():
                        $institutionAdminEditUserRequest = $institutionAdminEditRequest->for('user')->processChild();

                        switch(true){
                            case $institutionAdminEditUserRequest->for('portfolio')->only():
                                return $processor->process('general-edit-user-portfolio');
                            break;
                        }
                    break;

                    case $institutionAdminEditRequest->for('stakeholder')->all():
                        $institutionAdminEditStakeholderRequest = $institutionAdminEditRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $institutionAdminEditStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-edit-stakeholder-portfolio');
                            break;
                        }
                    break;
                }
            break;

            case $institutionAdminRequest->for('view')->all():
                $institutionAdminViewRequest = $institutionAdminRequest->for('view')->processChild();

                switch(true){
                    case $institutionAdminViewRequest->for('user')->all():
                        $institutionAdminViewUserRequest = $institutionAdminViewRequest->for('user')->processChild();

                        switch(true){
                            case $institutionAdminViewUserRequest->for('portfolio')->only():
                                return $processor->process('general-view-user-portfolio');
                            break;
                        }
                    break;

                    case $institutionAdminViewRequest->for('stakeholder')->all():
                        $institutionAdminViewStakeholderRequest = $institutionAdminViewRequest->for('stakeholder')->processChild();

                        switch(true){
                            case $institutionAdminViewStakeholderRequest->for('portfolio')->only():
                                return $processor->process('general-view-stakeholder-portfolio');
                            break;
                        }
                    break;

                    case $institutionAdminViewRequest->for('support')->all():
                        $institutionAdminViewSupportRequest = $institutionAdminViewRequest->for('support')->processChild();

                        switch(true){
                            case $institutionAdminViewSupportRequest->for('ticket')->only():
                                return $processor->process('general-view-support-ticket');
                            break;

                            case $institutionAdminViewSupportRequest->for('tickets')->only():
                                return $processor->process('general-view-support-tickets');
                            break;
                        }
                    break;
                }
            break;

            case $institutionAdminRequest->for('add')->all():
                $institutionAdminAddRequest = $institutionAdminRequest->for('add')->processChild();

                switch(true){
                    case $institutionAdminAddRequest->for('support')->all():
                        $institutionAdminAddSupportRequest = $institutionAdminAddRequest->for('support')->processChild();

                        switch(true){
                            case $institutionAdminAddSupportRequest->for('ticket'):
                                return $processor->process('general-add-support-ticket');
                            break;
                        }
                    break;
                }
            break;
        }
    break;

    case $request->for('student')->all():
        $studentRequest = $request->for('student')->processChild();

        switch(true){
            case ($studentRequest->only() || $studentRequest->for('dashboard')->only()):
                return $processor->process('student-dashboard-home');
            break;

            case $studentRequest->for('take-test')->only():
                return $processor->process('student-take-test');
            break;

            case $studentRequest->for('edit')->all():
                $studentEditRequest = $studentRequest->for('edit')->processChild();
                switch(true){
                    case $studentEditRequest->for('user')->all():
                        $studentEditUserRequest = $studentEditRequest->for('user')->processChild();

                        switch(true){
                            case $studentEditUserRequest->for('portfolio')->only():
                                return $processor->process('general-edit-user-portfolio');
                            break;
                        }
                    break;
                }
            break;

            case $studentRequest->for('view')->all():
                $studentViewRequest = $studentRequest->for('view')->processChild();

                switch(true){
                    case $studentViewRequest->for('user')->all():
                        $studentViewUserRequest = $studentViewRequest->for('user')->processChild();

                        switch(true){
                            case $studentViewUserRequest->for('portfolio')->only():
                                return $processor->process('general-view-user-portfolio');
                            break;
                        }
                    break;

                    case $studentViewRequest->for('support')->all():
                        $studentViewSupportRequest = $studentViewRequest->for('support')->processChild();

                        switch(true){
                            case $studentViewSupportRequest->for('ticket')->only():
                                return $processor->process('general-view-support-ticket');
                            break;

                            case $studentViewSupportRequest->for('tickets')->only():
                                return $processor->process('general-view-support-tickets');
                            break;
                        }
                    break;
                }
            break;

            case $studentRequest->for('add')->all():
                $studentAddRequest = $studentRequest->for('add')->processChild();

                switch(true){
                    case $studentAddRequest->for('support')->all():
                        $studentAddSupportRequest = $studentAddRequest->for('support')->processChild();

                        switch(true){
                            case $studentAddSupportRequest->for('ticket'):
                                return $processor->process('general-add-support-ticket');
                            break;
                        }
                    break;
                }
            break;
        }
    break;

    case $request->for('get')->all():
        $getRequest = $request->for('get')->processChild();

        switch(true){
            case $getRequest->for('regions')->only():
                return $processor->process('get-regions');
            break;

            case $getRequest->for('lgas')->only():
                return $processor->process('get-lgas');
            break;

            case $getRequest->for('data')->only():
                return $processor->process('get-data');
            break;

            case $getRequest->for('secondary-schools')->only():
                return $processor->process('get-secondary-schools');
            break;

            case $getRequest->for('support')->all():
                $getSupportRequest = $request->for('support')->processChild();

                switch(true){
                    case $getSupportRequest->for('ticket')->all():
                        $getSupportTicketRequest = $request->for('ticket')->processChild();

                        switch(true){
                            case $getSupportTicketRequest->for('categories')->only():
                                return $processor->process('get-support-ticket-categories');
                            break;
                        }
                    break;
                }
                return $processor->process('get-secondary-schools');
            break;
        }
    break;

    case $request->for('update-data')->only():
        return $processor->process('update-data');
    break;

    case $request->for('faqs')->only():
        return $processor->process('faqs');
    break;
}

echo var_dump($request->getFullRequestUrl());
exit();
return $response->redirect('/404');