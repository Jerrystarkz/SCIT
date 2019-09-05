<?php

namespace Scit\Users;

use function GuzzleHttp\json_decode;


class Manager extends \Scit\General\Templates\General{

    public function isLogged(){
        return (int) $this->getSession()->use('')->get('isLogged');
    }

    public function logout(){
        $session = $this->getSession()->use('');
        $session->remove('userData')->remove('studentData')->remove('adminData')->remove('tokens')->remove('isLogged');
        return $this;
    }

    public function generateHashedPassword(string $password){
        $prepend = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->salts-->password-->prepend');
        $append = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->salts-->password-->append');
        return hash('sha512',"{$prepend}{$password}{$append}");
    }

    public function addStakeholderData(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');

        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        /*
        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('addStakeholderData')->verify();


        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }
        */

        $for = $request->get('for');
        $userType = $this->getSession()->get('userData-->type');
        $stakeholderType = \str_replace('-administrator','',$userType);

        if(!hash_equals($for,$stakeholderType)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $header = [
            "{$for}-name",
            "{$for}-unique-name",
            "{$for}-country",
            "{$for}-region",
            "{$for}-local-government-area",
            "{$for}-address",
            "{$for}-description"
        ];

        $validatorRules = [
            'required|min_len,5|max_len,150',
            'required|min_len,3|max_len,150',
            'required',
            'required',
            'required',
            'required|min_len,10|max_len,225',
            'required|min_len,20|max_len,225'
        ];

        $filterRules = [
            'trim|sanitize_string|lower_case',
            'trim|sanitize_email|lower_case',
            'trim',
            'trim',
            'trim',
            'trim|sanitize_string|lower_case',
            'trim|sanitize_string|lower_case'
        ];

        $validatorRules = array_combine($header,$validatorRules);
        $filterRules = array_combine($header,$filterRules);
        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($request->get());

        if($validatedData === false){
            return [
                'status' => 'error',
                'from' => 'general',
                'response' => $validator->get_errors_array()
            ];
        }

        $name = $this->getUtils()::getDataFromArray($validatedData,"{$for}-name");
        $uniqueName = $this->getUtils()::getDataFromArray($validatedData,"{$for}-unique-name");
        $countryId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,"{$for}-country"));
        $regionId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,"{$for}-region"));
        $lgaId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,"{$for}-local-government-area"));
        $address = $this->getUtils()::getDataFromArray($validatedData,"{$for}-address");
        $description = $this->getUtils()::getDataFromArray($validatedData,"{$for}-description");

        $data = [
            'uniqueName' => &$uniqueName,
            'name' => &$name,
            'countryId' => &$countryId,
            'regionId' => &$regionId,
            'lgaId' => &$lgaId,
            'address' => &$address,
            'description' => &$description,
            'adminUserId' => $this->getSession()->get('userData-->id'),
            'adminType' => $this->getSession()->get('userData-->type'),
        ];

        $stakeholderDb = ($for == 'secondary-school' ? 'secondary_schools' : ($for == 'internship-provider' ? 'internship_providers' : ($for == 'institution' ? 'institutions' : '')));
        if(!$stakeholderDb){
            return $this->getUtils()::getResponseFor('invalid-response');
        }

        $query = <<<"query"
            begin not atomic
                declare stakeholderId int default 0;
                declare adminId int default 0;

                `inner_process`: begin
                    if not (select 1 from lgas as lga join regions as region on (lga._region_id = region.id and region.id = :regionId) join countries as country on (region._country_id = country.id and country.id = :countryId) where lga.id = :lgaId limit 1) then

                        select '{"status":"error","response":"Invalid Geo information provided... please select appropriate values","from":"general"}' as response;
                        leave `inner_process`;
                    end if;

                    if (select 1 from {$stakeholderDb} as stakeholder where stakeholder._unique_name = :uniqueName) then

                        select '{"status":"error","response":"Unique name is already in use","from":"general"}' as response;
                        leave `inner_process`;
                    end if;

                    start transaction;

                    insert into {$stakeholderDb} (_unique_name,_name,_country_id,_region_id,_lga_id,_address,_description,_is_approved,_is_blocked,_approval_request_date,_added_by) values (:uniqueName,:name,:countryId,:regionId,:lgaId,:address,:description,0,0,now(),0);

                    set stakeholderId = last_insert_id();
                    if not stakeholderId then

                        rollback;
                        select '{"status":"error","response":"An error occured while adding stakeholder data.","from":"general"}' as response;
                        leave `inner_process`;
                    end if;

                    insert into administrators (_user_id,_stakeholder_id,_type,_is_approved,_is_blocked,_approval_request_date) values (:adminUserId,stakeholderId,:adminType,0,0,now());

                    set adminId = last_insert_id();
                    if not adminId then

                        rollback;
                        select '{"status":"error","response":"An error occured while adding stakeholder data..","from":"general"}' as response;
                        leave `inner_process`;
                    end if;

                    update {$stakeholderDb} set _added_by = adminId where id = stakeholderId;
                    if not row_count() then

                        rollback;
                        select '{"status":"error","response":"An error occured while adding stakeholder data...","from":"general"}' as response;
                        leave `inner_process`;
                    end if;

                    update users set _token = substr(unix_timestamp(),1,15),_has_complete_registration = 1 where id = :adminUserId;
                    if not row_count() then

                        rollback;
                        select '{"status":"error","response":"An error occured while adding stakeholder data...."}' as response;
                        leave `inner_process`;
                    end if;

                    commit;
                    select '{"status":"ok","response":"Stakeholder Data added succesfully"}' as response;
                    leave `inner_process`;
                end;
            end;
query;

        $result = $this->getDatabase()->prepare($query)->bind($data)->result(true);
        if($result){
            return \json_decode($result[0]['response'],true);
        }
        return $this->getUtils()::getResponseFor('invalid-request');
    }

    public function processEmailVerification(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');

        $validatorRules = [
            'verificationHash' => 'required',
            'googleCaptchaToken' => 'required'
        ];

        $filterRules = [
            'verificationHash' => 'trim|sanitize_string',
            'googlecaptchaToken' => 'required'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($request->get());

        if($validatedData === false){
            return [
                'status' => 'error',
                'from' => 'general',
                'response' => 'Ooops an error occured while verifying email'
            ];
        }

        $verificationHash = $this->getUtils()::getDataFromArray($validatedData,'verificationHash');
        $googleCaptchaToken = $this->getUtils()::getDataFromArray($validatedData,'googleCaptchaToken');

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v2')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $googleCaptchaCheck;
        }

        $emailVerificationData = $this->getEmailVerificationData();
        if($emailVerificationData['status'] === 'error'){
            return $emailVerificationData;
        }

        if($emailVerificationData['empty']){
            return [
                'status' => 'error',
                'response' => 'Ooops no verification mail has been sent to this account'
            ];
        }

        $tokens = $this->getUtils()::getDataFromArray($emailVerificationData,'data-->tokens');
        if(!(is_array($tokens) && isset(array_flip($tokens)[$verificationHash]))){
            return [
                'status' => 'error',
                'response' => 'Ooops... An error occured'
            ];
        }

        $session = $this->getSession();
        $db =  $this->getDatabase();
        $userType = $session->get('userData-->type');
        $userId = $session->get('userData-->id');
        $addAdminData = 0;

        if(isset(array_flip([
            'general-administrator',
            'support-administrator'
        ])[$userType])){
            $addAdminData = 1;
        }

        $result = $this->getDatabase()->prepare(<<<"query"
            begin not atomic

                `inner_process`: begin
                    start transaction;

                    if :addAdminData then
                        insert into administrators (_user_id,_stakeholder_id,_type,_is_approved,_is_blocked,_approval_request_date) values (:adminUserId,0,:adminType,0,0,now());

                        if not last_insert_id() then

                            rollback;
                            select '{"status":"error","response":"An error occured while verifying account."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    update users set _token = substr(unix_timestamp(),1,15),_is_verified = 1 where id = :adminUserId;

                    if not row_count() then
                        rollback;
                        select '{"status":"error","response":"An error occured while verifying account.."}' as response;
                        leave inner_process;
                    end if;

                    commit;
                    select '{"status":"ok","response":"Account verified succesfully"}' as response;
                    leave inner_process;
                end;
            end;
query
        )->bind([
            'adminUserId' => &$userId,
            'adminType' => &$userType,
            'addAdminData' => &$addAdminData
        ])->result();

        if($result){
            $result = json_decode($result[0]['response'],true);

            if(is_array($result)){
                return $result;
            }
        }
        return $this->getUtils()::getResponseFor('malformed-db-response');
    }

    public function sendEmailVerificationData(){
        $session = $this->getSession();
        $db = $this->getDatabase();
        $token = $this->getUtils()::random(60);
        $mailer = $this->getUtils()->init('General-Mailer');

        $verificationUrl = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->webUrl').'/user/account/verification?withToken='.$token;
        $emailHtmlBody = $this->getUtils()->init('General-HtmlManager')->render('email/verification.html',[
            'data' => [
                'verification' => [
                    'url' => &$verificationUrl
                ]
            ]
        ]);

        $data = [
            'userId' => $session->get('userData-->id')
        ];

        $pointerId = 0;
        $holderId = 0;

        $result = $db->prepare('select emailVerification._data as emailVerificationData from email_verifications as emailVerification where emailVerification._user_id = :userId limit 1;')->bind($data)->result();

        $tokens = [];
        $totalSent = 0;
        if($result){
            $previousVerificationData = json_decode($result[0]['emailVerificationData'],true);

            $maximum = (int) $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->verification-->email-->maximum');
            $interval = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->verification-->email-->interval');

            $lastSent = $this->getUtils()::getDataFromArray($previousVerificationData,'last_sent_at') ?: date('c');
            $totalSent = (int) $this->getUtils()::getDataFromArray($previousVerificationData,'total_sent');
            $tokens = $this->getUtils()::getDataFromArray($previousVerificationData,'tokens');

            if(!is_numeric($totalSent)){
                $totalSent = 0;
            }

            if(!is_array($tokens)){
                $tokens = [];
            }

            if($totalSent >= $maximum){
                return [
                    'status' => 'error',
                    'response' => 'Ooops, you have reached the maximum allowed limit for verification'
                ];
            }

            if(time() < strtotime("+ {$interval}",strtotime($lastSent))){
                return [
                    'status' => 'error',
                    'response' => "Ooops, you can only resend verification email after {$interval} from the the time the last email was sent"
                ];
            }
        }

        /* Send verification email here */
        $mailer = $this->getUtils()->init('General-Mailer');
        $mailer->isHTML(true);
        $mailer->setFrom($this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->accounts-->verification-->email'),$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->accounts-->verification-->name'));
        $mailer->addAddress($session->get('userData-->email'),$session->get('userData-->name'));
        $mailer->Subject = 'Email Verification Request';

        $mailer->Body = $emailHtmlBody;
        $mailer->alt = "Please copy the following url to a web browser to verify your account {$verificationUrl}";
        $mailer->isSMTP();
        $mailer->SMTPDebug = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->debug-->level');
        $mailer->Host = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->host');
        $mailer->Port = (int) $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->port');
        $mailer->timeout = (int) $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->timeout');
        $mailer->SMTPSecure = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->connectionType');
        $mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->ssl-->verify_peer'),
                'verify_depth' => $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->ssl-->verify_depth'),
                'allow_self_signed' => $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->ssl-->allow_self_signed'),
                'peer_name' => $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->ssl-->peer_name'),
                'verify_peer_name' => $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->ssl-->verify_peer_name')
            ],
        ];
        $mailer->SMTPAuth = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->useAuthentication');
        $mailer->Username = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->accounts-->verification-->username');
        $mailer->Password = $this->getUtils()::getDataFromArray($this->managers,'adminSettings-->mail-->accounts-->verification-->password');

        /*After succesfull send, do this*/
        if($mailer->send()){
            $totalSent = ++$totalSent;
            $tokens[] = $token;
            $verificationData = [
                'last_sent_at' => date('c'),
                'total_sent' => $totalSent,
                'tokens' => $tokens
            ];

            $this->getUtils()::setDataInArray($data,'verificationData',json_encode($verificationData));
            $this->getUtils()::setDataInArray($data,'newEntry',($totalSent == 1  ? 1 : 0));

            $query = <<<'query'
                begin not atomic
                    declare newEntry tinyint default :newEntry;
                    `inner_process`: begin
                        if (newEntry) then
                            insert into email_verifications (_user_id,_data) values (:userId,:verificationData);
                        else
                            update email_verifications set _data = :verificationData where _user_id = :userId;
                        end if;

                        if not row_count() then

                            select '{"status":"error","response":"An error occurred while saving mail data.."}' as response;
                            leave `inner_process`;
                        end if;

                        select '{"status":"ok","response":"Email sent succesfully"}' as response;
                    end;
                end;
query;

            $result = $db->prepare($query)->bind($data)->result();
            if($result){
                $result = json_decode($result[0]['response'],true);
                if(is_array($result)){
                    return $result;
                }
            }
            return $this->getUtils()::getResponseFor('malformed-db-response');
        }
        return [
            'status' => 'error',
            'response' => 'Ooops a mail server error occured... please try again'
        ];
    }

    public function getEmailVerificationData(){
        $query = <<<'query'
            select emailVerification._data as emailVerificationData from email_verifications as emailVerification where emailVerification._user_id = :userId limit 1;
query;
        $session = $this->getSession();
        $data = [
            'userId' => $session->get('userData-->id')
        ];

        $result = $this->getDatabase()->prepare($query)->bind($data)->result(true);
        if($result){
            return [
                'status' => 'ok',
                'empty' => false,
                'data' => json_decode($result[0]['emailVerificationData'],true)
            ];
        }

        return [
            'status' => 'ok',
            'empty' => true
        ];
    }

    public function register(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');

        $personality = strtolower((string) $request->get('personality'));
        if(!isset(array_flip([
            'secondary-school-administrator',
            'student',
            'internship-provider-administrator',
            'institution-administrator',
            'general-administrator',
            'support-administrator'
        ])[$personality])){
            return [
                'status' => 'error',
                'type' => 'parameters',
                'from' => 'personality',
                'response' => "Oooops invalid user"
            ];
        }

        $validatorRules = [
            'name' => 'required|min_len,5|max_len,150',
            'password' => 'required|min_len,5',
            'country' => 'required',
            'region' => 'required',
            'local_government_area' => 'required',
            'confirm_password' => 'required|min_len,5|equalsfield,password'
        ];

        $filterRules = [
            'name' => 'trim|sanitize_string|lower_case'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($request->get());

        if($validatedData === false){
            return [
                'status' => 'error',
                'type' => 'parameters',
                'from' => 'general',
                'response' => $validator->get_errors_array()
            ];
        }

        $name = $this->getUtils()::getDataFromArray($validatedData,'name');
        $countryId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'country'));
        $regionId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'region'));
        $lgaId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'local_government_area'));
        $password = $request->get('password');
        $confirm_password = $request->get('confirm_password');

        if(!hash_equals($password,$confirm_password)){
            return [
                'status' => 'error',
                'type' => 'parameters',
                'from' => 'confirm_password',
                'response' => "Password do not match..."
            ];
        }

        $query = '';
        $data = [
            'name' => &$name,
            'password' => $this->generateHashedPassword($password),
            'countryId' => &$countryId,
            'regionId' => &$regionId,
            'lgaId' => &$lgaId,
            'schoolId' => null,
            'studentDob' => null,
            'studentLevel' => null,
            'studentUniqueName' => null,
            'userType' => &$personality,
            'hasCompleteRegistration' => 0,
            'email' => null,
            'phone' => null,
            'isVerified' => 0
        ];

        if ($personality === 'student'){
            $validatorRules = [
                'student_class' => 'required|contains_list,jss3;sss1;sss2;sss3',
                'student_unique_name' => 'required|min_len,3|max_len,255',
                'school_id' => 'required',
                'student_dob' => 'required'
            ];

            $filterRules = [
                'student_class' => 'trim|sanitize_string|lower_case',
                'student_unique_name' => 'trim|sanitize_string|lower_case',
                'school_unique_name' => 'trim|sanitize_string|lower_case'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run($request->get());

            if($validatedData === false){
                return [
                    'status' => 'error',
                    'type' => 'parameters',
                    'from' => 'general',
                    'response' => $validator->get_errors_array()
                ];
            }

            $schoolId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'school_id'));
            $studentClass = $this->getUtils()::getDataFromArray($validatedData,'student_class');
            $studentUniqueName = $this->getUtils()::getDataFromArray($validatedData,'student_unique_name');
            $studentDob = $this->getUtils()::getDataFromArray($validatedData,'student_dob');

            if(!$schoolId){
                return [
                    'status' => 'error',
                    'from' => 'school_id',
                    'response' => 'Invalid school'
                ];
            }

            if(($date = strtotime($studentDob)) === false){
                return [
                    'status' => 'error',
                    'from' => 'student_dob',
                    'response' => 'Invalid date of birth.. please follow the instructions'
                ];
            }

            $data = array_merge($data,[
                'studentUniqueName' => $studentUniqueName,
                'schoolId' => $schoolId,
                'studentLevel' => $studentClass,
                'studentDob' => date('Y-m-d H:i:s',$date),
                'isVerified' => 1,
                'hasCompleteRegistration' => 1
            ]);
        }else{
            $validatorRules = [
                'email' => 'required|valid_email',
                'phone' => 'required|numeric|min_len,9|max_len,15'
            ];

            $filterRules = [
                'email' => 'trim|sanitize_email|lower_case',
                'phone' => 'trim|sanitize_numbers'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run($request->get());

            if($validatedData === false){
                return [
                    'status' => 'error',
                    'type' => 'parameters',
                    'from' => 'general',
                    'response' => $validator->get_errors_array()
                ];
            }

            $email = $this->getUtils()::getDataFromArray($validatedData,'email');
            $phone = $this->getUtils()::getDataFromArray($validatedData,'phone');

            $data = array_merge($data,[
                'phone' => $phone,
                'email' => $email,
                'personality' => &$personality,
                'hasCompleteRegistration' => (isset(array_flip([
                    'general-administrator',
                    'support-administrator'
                ])[$personality]) ? 1 : 0)
            ]);
        }

        $query = <<<"query"
            begin not atomic
                declare userId int default 0;
                declare idHolder int default 0;
                declare secondarySchoolId int default 0;
                declare isUserVerified tinyint default 0;
                declare isUserApproved tinyint default 0;
                declare isUserBlocked tinyint default 0;
                declare hasCompleteRegistration tinyint default 0;

                `inner_process`: begin
                    if (:userType = 'student') then

                        if (select 1 from users as user where user._unique_name = :studentUniqueName) then

                            select '{"status":"error","response":"student unique name already exists... please try another name","from":"student_unique_name"}' as response;
                            leave `inner_process`;
                        end if;

                        select school.id into secondarySchoolId from secondary_schools as school where school.id = :schoolId and school._is_approved = 1 limit 1;
                        if not (secondarySchoolId) then

                            select '{"status":"error","response":"Invalid secondary school","from":"school_id"}' as response;
                            leave `inner_process`;
                        end if;

                    else

                        set idHolder = 0,isUserVerified = 0,isUserApproved = 0,isUserBlocked = 0,hasCompleteRegistration = 0;
                        select user.id,user._is_verified,user._is_approved,user._is_blocked,user._has_complete_registration into idHolder,isUserVerified,isUserApproved,isUserBlocked,hasCompleteRegistration from users as user where user._email = :email limit 1;

                        if idHolder then
                            select concat('{"status":"error","from":"email","response":"',concat('Email address already exists...',if(not isUserVerified,'If you are the owner, try verifying your account by following the instructions sent to your email.. Or try signing in to proceed',if(not hasCompleteRegistration,'If you are the owner, try signing in to upload stakeholder information',if(not isUserApproved,'If you are the owner, your account is pending approval be patient',if(isUserBlocked,'your account is blocked...',''))))),'"}') as response;
                            leave `inner_process`;
                        end if;

                        set idHolder = 0,isUserVerified = 0,isUserApproved = 0,isUserBlocked = 0,hasCompleteRegistration = 0;
                        select user.id,user._is_verified,user._is_approved,user._is_blocked,user._has_complete_registration into idHolder,isUserVerified,isUserApproved,isUserBlocked,hasCompleteRegistration from users as user where user._phone_number = :phone limit 1;

                        if idHolder then
                            select concat('{"status":"error","from":"phone","response":"',concat('Phone number already exists...',if(not isUserVerified,'If you are the owner, try verifying your account by following the instructions sent to your email.. Or try signing in to proceed',if(not hasCompleteRegistration,'If you are the owner, try signing in to upload stakeholder information',if(not isUserApproved,'If you are the owner, your account is pending approval be patient',if(isUserBlocked,'your account is blocked...',''))))),'"}') as response;
                            leave `inner_process`;
                        end if;

                    end if;

                    start transaction;

                    insert into users (_name,_unique_name,_phone_number,_email,_password,_country_id,_region_id,_lga_id,_token,_type,_is_verified,_has_complete_registration) values (:name,:studentUniqueName,:phone,:email,:password,:countryId,:regionId,:lgaId,substr(unix_timestamp(),1,15),:userType,:isVerified,:hasCompleteRegistration);
                    set userId = last_insert_id();

                    if not userId then

                        rollback;
                        select '{"status":"error","response":"An unknown error occured","from":"general"}' as response;
                        leave `inner_process`;
                    end if;

                    if (:userType = 'student') then
                        insert into students (_user_id,_secondary_school_id,_level,_dob,_is_approved,_is_blocked,_approval_request_date) values (userId,secondarySchoolId,:studentLevel,:studentDob,0,0,now());

                        if not last_insert_id() then
                            rollback;
                            select '{"status":"error","response":"An unknown error occured while linking user.","from":"general"}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    commit;
                    select concat('{"status":"ok","userId":',userId,'}') as response;
                    leave `inner_process`;
                end;
            end;
query;

        $result = $this->getDatabase()->prepare($query)->bind($data)->result(false);
        if($result){
            $result = \json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            if($result['status'] === 'error'){
                return $result;
            }

            if($result['status'] === 'ok'){
                return $this->logUserWith('id',[
                    'id' => $this->getUtils()::getDataFromArray($result,'userId')
                ]);
            }
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function login(){
        $session = $this->getSession();
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');

        $validatorRules = [
            'login' => 'required|min_len,3',
            'password' => 'required'
        ];

        $filterRules = [
            'login' => 'trim|sanitize_string|lower_case'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($request->get());

        if($validatedData === false){
            return [
                'status' => 'error',
                'type' => 'parameters',
                'from' => 'general',
                'response' => $validator->get_errors_array()
            ];
        }

        return $this->logUserWith('passphrase',[
            'login' => $this->getUtils()::getDataFromArray($validatedData,'login'),
            'password' => $request->get('password')
        ]);
    }

    public function logUserWith($what,array $data){
        $usersAdminManager = $this->getUtils()->init('Users-Admin-Manager');
        $filter = [];
        switch($what){
            case 'passphrase':
                $filter['use'] = $what;
                $filter['login'] = $this->getUtils()::getDataFromArray($data,'login');
                $filter['password'] = $this->getUtils()::getDataFromArray($data,'password');
            break;

            case 'id':
                $filter['use'] = $what;
                $filter['id'] = $this->getUtils()::getDataFromArray($data,'id');
            break;

            default:
                return $this->getUtils()::getResponseFor('invalid-request');
            break;
        }

        $session = $this->getSession();
        $session->set('userData',null);
        $session->set('approvedRoles',[]);
        $session->set('isLogged',0);
        $session->set('studentData',null);
        $session->set('adminData',null);

        $result = $usersAdminManager->get('user-portfolio-data',[
            'filter' => $filter
        ]);

        if($result){
            $status = $this->getUtils()::getDataFromArray($result,'status');
            if($status == 'error'){
                return $result;
            }

            $userData = $result['response'];
            $studentData = $this->getUtils()::getDataFromArray($userData,'student');
            $adminData = $this->getUtils()::getDataFromArray($userData,'adminProfiles');

            $session->set('userData',$userData);
            $session->set('userData-->id',$this->getUtils()->getDataOfHash($session->get('userData-->id')));
            $session->set('userData-->country-->id',$this->getUtils()->getDataOfHash($session->get('userData-->country-->id')));
            $session->set('userData-->region-->id',$this->getUtils()->getDataOfHash($session->get('userData-->region-->id')));
            $session->set('userData-->lga-->id',$this->getUtils()->getDataOfHash($session->get('userData-->lga-->id')));
            $session->set('userData-->data-->stateOfResidence-->id',($this->getUtils()->getDataOfHash($session->get('userData-->data-->stateOfResidence-->id')) ?: 0));

            if(is_array($studentData)){
                $session->set('userData-->student-->id',$this->getUtils()->getDataOfHash($session->get('userData-->student-->id')));
                $session->set('userData-->student-->school-->id',$this->getUtils()->getDataOfHash($session->get('userData-->student-->school-->id')));

                $session->set('studentData',null);
                $session->set('studentData',[
                    'id' => $session->get('userData-->student-->id'),
                    'schoolId' => $session->get('userData-->student-->school-->id'),
                    'level' => $session->get('userData-->student-->level'),
                    'isApproved' => $session->get('userData-->student-->isApproved'),
                    'isBlocked' => $session->get('userData-->student-->isBlocked'),
                    'isValid' => ($session->get('userData-->student-->isApproved') && (!$session->get('userData-->student-->isBlocked')))
                ]);

                $session->set('userType','student');
                if($session->get('studentData-->isValid')){
                    $session->set('approvedRoles[]','student');
                }
            }

            if(is_array($adminData)){
                foreach(array_keys($adminData) as $key){
                    $session->set("userData-->adminProfiles-->{$key}-->id",$this->getUtils()->getDataOfHash($session->get("userData-->adminProfiles-->{$key}-->id")));
                    if(is_array($session->get("userData-->adminProfiles-->{$key}-->stakeholder"))){
                        $session->set("userData-->adminProfiles-->{$key}-->stakeholder-->id",$this->getUtils()->getDataOfHash($session->get("userData-->adminProfiles-->{$key}-->stakeholder-->id")));
                        $session->set("userData-->adminProfiles-->{$key}-->stakeholder-->country-->id",$this->getUtils()->getDataOfHash($session->get("userData-->adminProfiles-->{$key}-->stakeholder-->country-->id")));
                        $session->set("userData-->adminProfiles-->{$key}-->stakeholder-->region-->id",$this->getUtils()->getDataOfHash($session->get("userData-->adminProfiles-->{$key}-->stakeholder-->region-->id")));
                        $session->set("userData-->adminProfiles-->{$key}-->stakeholder-->lga-->id",$this->getUtils()->getDataOfHash($session->get("userData-->adminProfiles-->{$key}-->stakeholder-->lga-->id")));
                    }

                    $session->set('adminData-->'.$session->get("userData-->adminProfiles-->{$key}-->type"),[
                        'id' => $session->get("userData-->adminProfiles-->{$key}-->id"),
                        'pointer' => $key,
                        'stakeholderId' => $session->get("userData-->adminProfiles-->{$key}-->stakeholder-->id") ?: 0,
                        'isApproved' => $session->get("userData-->adminProfiles-->{$key}-->isApproved"),
                        'isBlocked' => $session->get("userData-->adminProfiles-->{$key}-->isBlocked"),
                        'isValid' => ($session->get("userData-->adminProfiles-->{$key}-->isApproved") && (!$session->get("userData-->adminProfiles-->{$key}-->isBlocked")))
                    ]);

                    if($session->get('adminData-->'.$session->get("userData-->adminProfiles-->{$key}-->type").'-->isValid')){
                        $session->set('approvedRoles[]',$session->get("userData-->adminProfiles-->{$key}-->type"));
                    }
                }
                $session->set('userType','administrator');
            }

            $session->set('isLogged',1);
            return [
                'status' => 'ok',
                'response' => 'Logged succesfully'
            ];
        }

        return [
            'status' => 'error',
            'response' => 'No Record for user'
        ];
    }

    public function updateUserData(){
        $userId = $this->getSession()->get('userData-->id');
        $token = $this->getSession()->get('userData-->token');
        if(!$this->getDatabase()->prepare('select 1 from users as user where user.id = :userId and user._token = :token limit 1')->bind([
            'userId' => &$userId,
            'token' => &$token
        ])->result()){
            return $this->logUserWith('id',[
                'id' => $this->getSession()->get('userData-->id')
            ]);
        }
        return [
            'status' => 'ok',
            'response' => 'Data updated'
        ];
    }

    public function updateUserToken(){
        $this->getSession()->set('userData-->token',microtime(true));
        return [
            'status' => 'ok',
            'response' => 'Token updated succesfully'
        ];
    }

    public function hasPermissionAs($role){
        if($this->isLogged()){
            $session = $this->getSession();
            $approvedRoles = ($session->get('approvedRoles') ?: []);
            return isset(array_flip($approvedRoles)[$role]);
        }
        return false;
    }

    public function setDefaultRole($role){
        $session = $this->getSession();
        if($this->hasPermissionAs($role)){
            $session->set('userData-->type',$role);
            return [
                'status' => 'ok',
                'response' => 'Default role set succesfully'
            ];
        }
        return [
            'status' => 'error',
            'response' => 'Attempting to set un-authorized role'
        ];
    }

    public function sendChatData(){
        $session = $this->getSession();
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');

        $validatorRules = [
            'message' => 'required|min_len,1|max_len,255'
        ];

        $filterRules = [
            'message' => 'trim|sanitize_string'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($request->get());

        if($validatedData === false){
            return [
                'status' => 'error',
                'type' => 'parameters',
                'from' => 'general',
                'response' => 'Invalid chat message'
            ];
        }

        $message = $this->getUtils()::getDataFromArray($validatedData,'message');
        $from = $this->getUtils()->getHashOfData($request->get('from'));
        $to = $this->getUtils()->getHashOfData($request->get('to'));
        $senderType = $this->getUtils()->getHashOfData($request->get('sender_type'));
        $receiverType = $this->getUtils()->getHashOfData($request->get('receiver_type'));
        $chatId = $this->getUtils()->getHashOfData($request->get('chatId'));
        $from = $this->getUtils()->getHashOfData($request->get('from'));
        $to = $this->getUtils()->getHashOfData($request->get('to'));
        $senderType = $this->getUtils()->getHashOfData($request->get('sender_type'));
        $receiverType = $this->getUtils()->getHashOfData($request->get('receiver_type'));

        if(!(is_numeric($chatId) || (is_numeric($from) && is_numeric($to) && is_string($senderType) && is_string($receiverType)))){
            return [
                'status' => 'error',
                'response' => 'Invalid chat routing'
            ];
        }

        if(!is_numeric($chatId) && ($this->getSession()->get('userType') == 'student')){
            return [
                'status' => 'error',
                'response' => 'Only administrators can start up messenger'
            ];
        }

        $query = <<<"query"
            begin not atomic
                declare chatId varchar(255) default :chatId;
                declare holderId int default 0;
                declare from varchar(255) default :from;
                declare to varchar(255) default :to;
                declare senderType varchar(255) default :senderType;
                declare receiverType varchar(255) default :receiverType;
                declare message varchar(255) default :message;
                declare chatData longtext;
                declare addedChatData longtext;

                `inner_process`: begin
                    if (char_length(chatId)) then
                        select chat.id,chat._data into holderId,chatData where chat.id = chatId limit 1;
                        if (holderId) then
                            set addedChatData = concat('{"message":"',message,'","date":"',now(),'"}');
                            set chatData = if(char_length(chatData),concat(substr(chatData,1,(char_length(chatData) - 1)),',',substr(addedChatData,2)),addedChatData);
                            update chats set _data = chatData where id = chatId;

                            if not row_count() then
                                select concat('{"status":"ok","data":"',,'"}') as response;
                            end if;
                    end if;
                end;
            end;
query;

    }

    public function handleChatRequest(){
        $request = $this->getUtils()->init('General-Request');
        $session = $this->getSession();
        $search = $request->get('query');

        switch($query){
            case 'general-administrators':

            break;
        }
    }

    public function getChatMetaData(){
        $session = $this->getSession();
        $userType = $session->get('userType');

        $fromList = [];
        $sendList = [];
        $userTypeList = [];

        if($userType === 'student'){
            $fromList[] = [
                'name' => 'Student',
                'data' => json_encode([
                    'pointer' => $this->getUtils()->getHashOfData($session->get('studentData-->id')),
                    'type' => 'student'
                ])
            ];

            $sendList = [];
            $userTypeList = [];
        }

        if($userType === 'administrator'){
            $roles = $session->get('adminData');
            if(is_array($roles)){
                foreach($roles as $adminName => $data){
                    if($data['isApproved']){
                        if(!isset(array_flip([
                            'general-administrator',
                            'support-administrator'
                        ])[$adminName])){
                            $stakeholderName = str_replace('-administrator','',$adminName);
                            $fromList[] = [
                                'name' => ucwords(implode(' ',explode('-',$stakeholderName))),
                                'data' => json_encode([
                                    'pointer' => $this->getUtils()->getHashOfData($data['stakeholderId']),
                                    'type' => $stakeholderName
                                ])
                            ];
                        }
                        $fromList[] = [
                            'name' => ucwords(implode(' ',explode('-',$adminName))),
                            'data' => json_encode([
                                'pointer' => $this->getUtils()->getHashOfData($data['id']),
                                'type' => $adminName
                            ])
                        ];
                    }
                }
            }
        }

        $sendList = [
            [
                'name' => 'All Students',
                'data' => json_encode([
                    'type' => 'all-student'
                ])
            ],
            [
                'name' => 'All Support Administrator',
                'data' => json_encode([
                    'type' => 'all-support-administrator'
                ])
            ],
            [
                'name' => 'All Internship Provider',
                'data' => json_encode([
                    'type' => 'all-internship-provider'
                ])
            ],
            [
                'name' => 'All Internship Provider Administrator',
                'data' => json_encode([
                    'type' => 'all-internship-provider-administrator'
                ])
            ],
            [
                'name' => 'All Secondary School',
                'data' => json_encode([
                    'type' => 'all-secondary-school'
                ])
            ],
            [
                'name' => 'All Secondary School Administrator',
                'data' => json_encode([
                    'type' => 'all-secondary-school-administrator'
                ])
            ],
        ];

        return [
            'sendList' => $sendList,
            'fromList' => $fromList
        ];
    }
}