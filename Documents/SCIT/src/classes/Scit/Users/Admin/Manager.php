<?php

namespace Scit\Users\Admin;

class Manager extends \Scit\General\Templates\Manager{

    public function addSubject(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateSubjects')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare totalSuccess bigint default 0;
                declare existsDb longtext;
                declare errorsDb longtext;
                declare idHolder bigint default 0;
                declare subjectName longtext;
                declare subjectId bigint default 0;
                declare professionId bigint default 0;
                declare totalProfession bigint default 0;

                set existsDb = '[',errorsDb = '[';
query;
        $formData = $request->get('formData');
        $invalids = [];

        foreach($formData as &$subject){
            $validatorRules = [
                'name' => 'required|min_len,3|max_len,255'
            ];

            $filterRules = [
                'name' => 'trim|sanitize_string|lower_case'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run([
                'name' => &$subject['name']
            ]);

            if($validatedData === false){
                $invalids[] = array_merge($validator->sanitize($subject),['errorReason' => $validator->get_errors_array()['name']]);
                continue;
            }

            $name = $this->getUtils()::getDataFromArray($validatedData,'name');

            $subjectData = json_decode($subject['data'],true);
            if(!is_array($subjectData)){
                $subjectData = [];
            }

            $subjectProfessionsQuery = '';
            if(count($subjectData)){
                $subjectProfessionsQuery = 'set totalProfession = 0;';
                foreach ($subjectData as $key => $data) {
                    if(!\is_int($key)){
                        continue;
                    }
                    $order = ((int) $key + 1);
                    $weight = $data['weight'];
                    $professionId = $this->getUtils()->getDataOfHash($data['professionId']);
                    $subjectProfessionsQuery .= <<<"query"
                        set professionId = {$db->quote($professionId)};
                        if (select 1 from professions_db where id = professionId) then
                            insert into subjects_professions (_subject_id,_profession_id,_weight,_order,_added_by) values (subjectId,professionId,{$db->quote($weight)},{$db->quote($order)},:userId);
                            if row_count() then
                                set totalProfession = (totalProfession + 1);
                            end if;
                        end if;
query;
                }
            }

            $query = <<<"query"
                {$query}
                set idHolder = 0,subjectId = 0,subjectName = {$db->quote($name)};
                select subject.id into idHolder from subjects_db as subject where subject._name = subjectName limit 1;

                if idHolder then
                    set existsDb = concat(existsDb,if(existsDb = '[','',','),'{"name":"',subjectName,'"}');
                else
                    start transaction;
                    insert into subjects_db (_name,_added_by) values (subjectName,:userId);
                    set subjectId = last_insert_id();
                    if subjectId then
                        {$subjectProfessionsQuery}
                        commit;
                        set totalSuccess = (totalSuccess + 1);
                    else
                        rollback;
                        set errorsDb = concat(errorsDb,if(errorsDb = '[','',','),'{"name":"',subjectName,'"}');
                    end if;
                end if;
query;
        }

        $query = <<<"query"
            {$query}
            set existsDb = concat(existsDb,']'),errorsDb = concat(errorsDb,']');
            select concat('{"added":"',totalSuccess,'","exists":',existsDb,',"errors":',errorsDb,'}') as response;
            end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(true);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => array_merge($result,['invalids' => $invalids])
            ];
        }

        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeSubjects(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateSubjects')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare subjectId bigint default 0;
                declare totalRemoved bigint default 0;
                declare totalError bigint default 0;
query;
        $subjectIds = json_decode($request->get('subjectIds'),true);
        if(!(is_array($subjectIds) && count($subjectIds))){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        foreach($subjectIds as $subjectId){
            $subjectId = $this->getUtils()->getDataOfHash($subjectId);
            $query .= <<<"query"
                set subjectId = {$db->quote($subjectId)};
                if (select 1 from subjects_db where id = subjectId) then
                    delete from subjects_professions where _subject_id = subjectId;
                    delete from subjects_db where id = subjectId;
                    if row_count() then
                        set totalRemoved = (totalRemoved + 1);
                    else
                        set totalError = (totalError + 1);
                    end if;
                else
                    set totalError = (totalError + 1);
                end if;
query;
        }

        $query = <<<"query"
            {$query}
            select concat('{"totalRemoved":"',totalRemoved,'","totalError":"',totalError,'"}') as response;
            end;
query;

        $result = $db->query($query)->result(true);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => $result
            ];
        }

        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateSubject(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateSubjects')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/
        $professionData = json_decode($request->get('professionData'),true);
        if(!is_array($professionData)){
            $professionData = [];
        }

        $validatorRules = [
            'subject_id' => 'required',
            'subject_name' => 'required|min_len,3|max_len,255',
            'profession_data' => 'required|valid_array_size_greater,1'
        ];

        $filterRules = [
            'subject_name' => 'trim|sanitize_string|lower_case'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run([
            'subject_id' => $request->get('subjectId'),
            'subject_name' => $request->get('subjectName'),
            'profession_data' => $professionData
        ]);

        if($validatedData === false){
            $errors = $validator->get_errors_array();
            $errorText = '';
            foreach ($errors as $error) {
                $errorText .= $error.'; ';
            }
            return [
                'status' => 'error',
                'response' => $errorText
            ];
        }

        $subjectId = $this->getUtils()->getDataOfHash($request->get('subjectId'));
        $subjectName = $this->getUtils()::getDataFromArray($validatedData,'subject_name');
        $subjectProfessionsQuery = 'set totalProfession = 0;';

        foreach($professionData as $key => $data){
            if(!\is_int($key)){
                continue;
            }
            $order = ((int) $key + 1);
            $weight = $data['weight'];
            $professionId = $this->getUtils()->getDataOfHash($data['professionId']);
            $subjectProfessionsQuery .= <<<"query"
                set professionId = {$db->quote($professionId)};
                if (select 1 from professions_db where id = professionId) then
                    insert into subjects_professions (_subject_id,_profession_id,_weight,_order,_added_by) values (subjectId,professionId,{$db->quote($weight)},{$db->quote($order)},:userId);
                    if row_count() then
                        set totalProfession = (totalProfession + 1);
                    end if;
                end if;
query;
        }

        $query = <<<"query"
            begin not atomic
                declare subjectName varchar(255) default {$db->quote($subjectName)};
                declare subjectId bigint(255) default {$db->quote($subjectId)};
                declare professionId bigint default 0;
                declare totalProfession bigint default 0;

                if (select 1 from subjects_db where id = subjectId) then
                    start transaction;
                    update subjects_db set _name = subjectName where id = subjectId;
                    delete from subjects_professions where _subject_id = subjectId;
                    {$subjectProfessionsQuery}
                    commit;
                    select '{"status":"ok","response":"Updated succesfully"}' as response;
                else
                    select '{"status":"error","response":"Invalid subject"}' as response;
                end if;
            end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(true);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addDiscipline(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateDisciplines')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare totalSuccess bigint default 0;
                declare existsDb longtext;
                declare errorsDb longtext;
                declare idHolder bigint default 0;
                declare disciplineName longtext;
                declare disciplineId bigint default 0;
                declare professionId bigint default 0;
                declare totalProfession bigint default 0;

                set existsDb = '[',errorsDb = '[';
query;
        $formData = $request->get('formData');

        $invalids = [];

        foreach($formData as &$discipline){
            $validatorRules = [
                'name' => 'required|min_len,3|max_len,255'
            ];

            $filterRules = [
                'name' => 'trim|sanitize_string|lower_case'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run([
                'name' => &$discipline['name']
            ]);

            if($validatedData === false){
                $invalids[] = array_merge($validator->sanitize($discipline),['errorReason' => $validator->get_errors_array()['name']]);
                continue;
            }

            $name = $this->getUtils()::getDataFromArray($validatedData,'name');

            $disciplineData = json_decode($discipline['data'],true);
            if(!is_array($disciplineData)){
                $disciplineData = [];
            }

            $disciplineProfessionsQuery = '';
            if(count($disciplineData)){
                $disciplineProfessionsQuery = 'set totalProfession = 0;';
                foreach ($disciplineData as $key => $data) {
                    if(!\is_int($key)){
                        continue;
                    }
                    $order = ((int) $key + 1);
                    $weight = $data['weight'];
                    $professionId = (int) $this->getUtils()->getDataOfHash($data['professionId']);
                    $disciplineProfessionsQuery .= <<<"query"
                        set professionId = {$db->quote($professionId)};
                        if (select 1 from professions_db where id = professionId) then
                            insert into disciplines_professions (_discipline_id,_profession_id,_weight,_order,_added_by) values (disciplineId,professionId,{$db->quote($weight)},{$db->quote($order)},:userId);
                            if row_count() then
                                set totalProfession = (totalProfession + 1);
                            end if;
                        end if;
query;
                }
            }

            $query = <<<"query"
                {$query}
                set idHolder = 0,disciplineId = 0,disciplineName = {$db->quote($name)};
                select discipline.id into idHolder from disciplines_db as discipline where discipline._name = disciplineName limit 1;

                if idHolder then
                    set existsDb = concat(existsDb,if(existsDb = '[','',','),'{"name":"',disciplineName,'"}');
                else
                    start transaction;
                    insert into disciplines_db (_name,_added_by) values (disciplineName,:userId);
                    set disciplineId = last_insert_id();
                    if disciplineId then
                        {$disciplineProfessionsQuery}
                        set totalSuccess = (totalSuccess + 1);
                    else
                        rollback;
                        set errorsDb = concat(errorsDb,if(errorsDb = '[','',','),'{"name":"',disciplineName,'"}');
                    end if;
                end if;
query;
        }

        $query = <<<"query"
            {$query}
            set existsDb = concat(existsDb,']'),errorsDb = concat(errorsDb,']');
            select concat('{"added":"',totalSuccess,'","exists":',existsDb,',"errors":',errorsDb,'}') as response;
            end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(true);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => array_merge($result,['invalids' => $invalids])
            ];
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeDisciplines(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateDisciplines')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare disciplineId bigint default 0;
                declare totalRemoved bigint default 0;
                declare totalError bigint default 0;
query;
        $disciplineIds = json_decode($request->get('disciplineIds'),true);
        if(!(is_array($disciplineIds) && count($disciplineIds))){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        foreach($disciplineIds as $disciplineId){
            $disciplineId = $this->getUtils()->getDataOfHash($disciplineId);
            $query .= <<<"query"
                set disciplineId = {$db->quote($disciplineId)};
                if (select 1 from disciplines_db where id = disciplineId) then
                    delete from disciplines_professions where _discipline_id = disciplineId;
                    delete from disciplines_db where id = disciplineId;
                    if row_count() then
                        set totalRemoved = (totalRemoved + 1);
                    else
                        set totalError = (totalError + 1);
                    end if;
                else
                    set totalError = (totalError + 1);
                end if;
query;
        }

        $query = <<<"query"
            {$query}
            select concat('{"totalRemoved":"',totalRemoved,'","totalError":"',totalError,'"}') as response;
            end;
query;

        $result = $db->query($query)->result(true);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => $result
            ];
        }

        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateDiscipline(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateDisciplines')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/
        $professionData = json_decode($request->get('professionData'),true);
        if(!is_array($professionData)){
            $professionData = [];
        }

        $validatorRules = [
            'discipline_id' => 'required',
            'discipline_name' => 'required|min_len,3|max_len,255',
            'profession_data' => 'required|valid_array_size_greater,1'
        ];

        $filterRules = [
            'discipline_name' => 'trim|sanitize_string|lower_case'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run([
            'discipline_id' => $request->get('disciplineId'),
            'discipline_name' => $request->get('disciplineName'),
            'profession_data' => $professionData
        ]);

        if($validatedData === false){
            $errors = $validator->get_errors_array();
            $errorText = '';
            foreach ($errors as $error) {
                $errorText .= $error.'; ';
            }
            return [
                'status' => 'error',
                'response' => $errorText
            ];
        }

        $disciplineId = $this->getUtils()->getDataOfHash($request->get('disciplineId'));
        $disciplineName = $this->getUtils()::getDataFromArray($validatedData,'discipline_name');
        $disciplineProfessionsQuery = 'set totalProfession = 0;';

        foreach($professionData as $key => $data){
            if(!\is_int($key)){
                continue;
            }
            $order = ((int) $key + 1);
            $weight = $data['weight'];
            $professionId = $this->getUtils()->getDataOfHash($data['professionId']);
            $disciplineProfessionsQuery .= <<<"query"
                set professionId = {$db->quote($professionId)};
                if (select 1 from professions_db where id = professionId) then
                    insert into disciplines_professions (_discipline_id,_profession_id,_weight,_order,_added_by) values (disciplineId,professionId,{$db->quote($weight)},{$db->quote($order)},:userId);
                    if row_count() then
                        set totalProfession = (totalProfession + 1);
                    end if;
                end if;
query;
        }

        $query = <<<"query"
            begin not atomic
                declare disciplineName varchar(255) default {$db->quote($disciplineName)};
                declare disciplineId bigint(255) default {$db->quote($disciplineId)};
                declare professionId bigint default 0;
                declare totalProfession bigint default 0;

                if (select 1 from disciplines_db where id = disciplineId) then
                    start transaction;
                    if (select 1 from disciplines_db where ((_name = disciplineName) and (id != disciplineId)) limit 1) then
                        select '{"status":"error","response":"Discipline already exists.. please update that record"}' as response;
                    else
                        update disciplines_db set _name = disciplineName where id = disciplineId;
                        delete from disciplines_professions where _discipline_id = disciplineId;
                        {$disciplineProfessionsQuery}
                        commit;
                        select '{"status":"ok","response":"Updated succesfully"}' as response;
                    end if;
                else
                    select '{"status":"error","response":"Invalid discipline"}' as response;
                end if;
            end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(true);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addProfession(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateProfessions')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare existsDb longtext;
                declare errorsDb longtext;
                declare idHolder bigint default 0;
                declare totalInserted bigint default 0;
                declare professionName longtext;
                declare professionId bigint default 0;
                declare professionArtWeight mediumint(3) default 0;
                declare professionScienceWeight mediumint(3) default 0;
                declare disciplineId bigint default 0;
                declare disciplineWeight mediumInt(3) default 0;
                declare disciplineOrder bigint default 0;

                set existsDb = '[',errorsDb = '[';
                start transaction;
query;
        $formData = $request->get('formData');
        $invalids = [];

        foreach($formData as $pointer => &$professionData){
            $categories = \json_decode($professionData['categories'],true);
            $disciplines = \json_decode($professionData['disciplines'],true);
            $name = &$professionData['name'];

            if(!(is_array($categories) && is_array($disciplines))){
                return $this->getUtils()::getResponseFor('invalid-request');
            }

            $science = (int) $this->getUtils()::getDataFromArray($categories,'science') ?: 0;
            $art = (int) $this->getUtils()::getDataFromArray($categories,'art') ?: 0;

            $professionNameLength = strlen($name);
            if(!($professionNameLength && ($professionNameLength < 255))){
                $invalids[] = array_merge($validator->sanitize([
                    'name' => $name
                ]),[
                    'errorReason' => 'Invalid profession name'
                ]);
                continue;
            }

            if(($science < 0 || $science > 100) || ($art < 0 || $art > 100)){
                $invalids[] = array_merge($validator->sanitize([
                    'name' => $name
                ]),[
                    'errorReason' => 'Invalid category weights'
                ]);
                continue;
            }

            $name = strtolower($name);
            $query = <<<"query"
                {$query}
                savepoint `{$name}_{$pointer}`;
                set idHolder = 0,professionId = 0,professionName = {$db->quote($name)},professionArtWeight = {$db->quote($art)},professionScienceWeight = {$db->quote($science)};
                select profession.id into idHolder from professions_db as profession where profession._name = professionName limit 1;

                if idHolder then
                    rollback to `{$name}_{$pointer}`;
                    set existsDb = concat(existsDb,if(existsDb = '[','',','),'{"name":"',professionName,'"}');
                else
                    insert into professions_db (_name,_science_weight,_art_weight,_added_by) values (professionName,professionScienceWeight,professionArtWeight,:userId);
                    set professionId = last_insert_id();
                    if professionId then

query;
            if(count($disciplines)){
                foreach($disciplines as $disciplineId => &$options){
                    $disciplineId = $this->getUtils()->getDataOfHash($disciplineId);
                    $weight =  $options['weight'];

                    if($weight < 0 || $weight > 100){
                        $invalids[] = array_merge($validator->sanitize([
                            'name' => $name
                        ]),[
                            'errorReason' => 'A discipline in this profession was not added due to invalid weight'
                        ]);
                    }

                    $query = <<<"query"
                        {$query}
                        savepoint `{$name}_{$pointer}_{$disciplineId}`;
                        set disciplineOrder = (disciplineOrder + 1),idHolder = 0,disciplineId = {$db->quote($disciplineId)},disciplineWeight = {$db->quote($weight)};
                        select 1 into idHolder from disciplines_db where id = disciplineId limit 1;

                        if idHolder then
                            insert into disciplines_professions(_discipline_id,_profession_id,_weight,_order,_added_by) values (disciplineId,professionId,disciplineWeight,disciplineOrder,:userId);
                            if last_insert_id() then
                                set totalInserted = (totalInserted + 1);
                            else
                                rollback to `{$name}_{$pointer}_{$disciplineId}`;
                                set errorsDb = concat(errorsDb,if(errorsDb = '[','',','),'{"name":"',professionName,'","errorText":"Ooops a fatal database error occuured"}');
                            end if;
                        else
                            rollback to `{$name}_{$pointer}_{$disciplineId}`;
                            set errorsDb = concat(errorsDb,if(errorsDb = '[','',','),'{"name":"',professionName,'","errorText":"Invalid discipline"}');
                        end if;
query;
                }
            }else{
                $query = <<<"query"
                    {$query}
                    set totalInserted = (totalInserted + 1);
query;
            }

            $query = <<<"query"
                {$query}
                else
                    rollback to `{$name}_{$pointer}`;
                    set errorsDb = concat(errorsDb,if(errorsDb = '[','',','),'{"name":"',professionName,'"}');
                end if;
            end if;
query;
        }

        $query = <<<"query"
            {$query}
            set existsDb = concat(existsDb,']'),errorsDb = concat(errorsDb,']');
            commit;
            select concat('{"added":"',totalInserted,'","exists":',existsDb,',"errors":',errorsDb,'}') as response;
            end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(false);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => array_merge($result,['invalids' => $invalids])
            ];
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeProfessions(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateProfessions')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare professionId bigint default 0;
                declare totalRemoved bigint default 0;
                declare totalError bigint default 0;
query;
        $professionIds = json_decode($request->get('professionIds'),true);
        if(!(is_array($professionIds) && count($professionIds))){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        foreach($professionIds as $professionId){
            $professionId = $this->getUtils()->getDataOfHash($professionId);
            $query .= <<<"query"
                set professionId = {$db->quote($professionId)};
                if (select 1 from professions_db where id = professionId) then
                    delete from disciplines_professions where _profession_id = professionId;
                    delete from subjectss_professions where _profession_id = professionId;
                    delete from temperaments_professions where _profession_id = professionId;
                    delete from professions_db where id = professionId;
                    if row_count() then
                        set totalRemoved = (totalRemoved + 1);
                    else
                        set totalError = (totalError + 1);
                    end if;
                else
                    set totalError = (totalError + 1);
                end if;
query;
        }

        $query = <<<"query"
            {$query}
            select concat('{"totalRemoved":"',totalRemoved,'","totalError":"',totalError,'"}') as response;
            end;
query;

        $result = $db->query($query)->result(true);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => $result
            ];
        }

        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateProfession(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateProfessions')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $id = $request->get('id');
        $categories = \json_decode($request->get('categories'),true);
        $disciplines = \json_decode($request->get('disciplines'),true);
        $name = $request->get('name');

        $id = $this->getUtils()->getDataOfHash($id);
        if(!($id && strlen($id) && is_array($categories) && is_array($disciplines))){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $science = (int) $this->getUtils()::getDataFromArray($categories,'science') ?: 0;
        $art = (int) $this->getUtils()::getDataFromArray($categories,'art') ?: 0;

        $professionNameLength = strlen($name);
        if(!($professionNameLength && ($professionNameLength < 255))){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        if(($science < 0 || $science > 100) || ($art < 0 || $art > 100) || ($science == 0 && $art == 0)){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $name = strtolower($name);

        $query = <<<"query"
            begin not atomic
                declare idHolder bigint default 0;
                declare totalInserted bigint default 0;
                declare professionName longtext;
                declare professionId bigint default 0;
                declare professionArtWeight mediumint(3) default 0;
                declare professionScienceWeight mediumint(3) default 0;
                declare disciplineId bigint default 0;
                declare disciplineWeight mediumInt(3) default 0;
                declare disciplineOrder bigint default 0;

                start transaction;
                savepoint `{$name}`;
                set idHolder = 0,professionId = {$db->quote($id)},professionName = {$db->quote($name)},professionArtWeight = {$art},professionScienceWeight = {$science};

                if not (select 1 from professions_db where id = professionId limit 1) then
                    select '{"status":"error","response":"Ooops invalid profession"}' as response;
                else
                    if (select 1 from professions_db where ((_name = professionName) and (id != professionId)) limit 1) then
                        rollback to `{$name}`;
                        select '{"status":"error","response":"Ooops the profession already exists"}' as response;
                    else
                        delete from disciplines_professions where _profession_id = professionId;
                        update professions_db set _name = professionName,_science_weight = professionScienceWeight,_art_weight = professionArtWeight where id = professionId;

query;

        if(count($disciplines)){
            foreach($disciplines as $disciplineId => &$options){
                $disciplineId = $this->getUtils()->getDataOfHash($disciplineId);

                $weight =  $options['weight'];
                if($weight < 0 || $weight > 100){
                    continue;
                }

                $query .= <<<"query"
                    savepoint `{$name}_{$disciplineId}`;
                    set disciplineOrder = (disciplineOrder + 1),idHolder = 0,disciplineId = {$db->quote($disciplineId)},disciplineWeight = {$db->quote($weight)};
                    select 1 into idHolder from disciplines_db where id = disciplineId limit 1;

                    if idHolder then
                        insert into disciplines_professions(_discipline_id,_profession_id,_weight,_order,_added_by) values (disciplineId,professionId,disciplineWeight,disciplineOrder,:userId);
                        if last_insert_id() then
                            set totalInserted = (totalInserted + 1);
                        else
                            rollback to `{$name}_{$disciplineId}`;
                        end if;
                    else
                        rollback to `{$name}_{$disciplineId}`;
                    end if;
query;
            }
        }

        $query .= <<<"query"
                    commit;
                    select '{"status":"ok","response":"Updated succesfully"}' as response;
                end if;
            end if;
        end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(false);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addTemperament(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateTemperaments')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare totalSuccess bigint default 0;
                declare existsDb longtext;
                declare errorsDb longtext;
                declare idHolder bigint default 0;
                declare temperamentName longtext;
                declare temperamentData longtext;
                declare temperamentId bigint default 0;
                declare professionId bigint default 0;
                declare totalProfession bigint default 0;

                set existsDb = '[',errorsDb = '[';
query;
        $formData = $request->get('formData');
        $invalids = [];

        foreach($formData as &$temperament){
            $validatorRules = [
                'name' => 'required|min_len,3|max_len,255',
                'description' => 'required|min_len,5|max_len,2000'
            ];

            $filterRules = [
                'name' => 'trim|sanitize_string|lower_case',
                'description' => 'trim|sanitize_string|lower_case'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run([
                'name' => &$temperament['name'],
                'description' => &$temperament['description']
            ]);

            if($validatedData === false){
                $invalids[] = array_merge($validator->sanitize($temperament),['errorReason' => $validator->get_errors_array()['name']]);
                continue;
            }

            $name = $this->getUtils()::getDataFromArray($validatedData,'name');
            $temperamentData = json_encode([
                'description' => $this->getUtils()::getDataFromArray($validatedData,'description')
            ]);

            $professions = json_decode($temperament['professions'],true);
            if(!is_array($professions)){
                $professions = [];
            }

            $temperamentProfessionsQuery = '';
            if(count($professions)){
                $temperamentProfessionsQuery = 'set totalProfession = 0;';
                foreach ($professions as $key => $professionId) {
                    if(!\is_int($key)){
                        continue;
                    }
                    $order = ((int) $key + 1);
                    $professionId = $this->getUtils()->getDataOfHash($professionId);
                    $temperamentProfessionsQuery .= <<<"query"
                        set professionId = {$db->quote($professionId)};
                        if (select 1 from professions_db where id = professionId) then
                            insert into temperaments_professions (_temperament_id,_profession_id,_order,_added_by) values (temperamentId,professionId,{$db->quote($order)},:userId);
                            if row_count() then
                                set totalProfession = (totalProfession + 1);
                            end if;
                        end if;
query;
                }
            }

            $query = <<<"query"
                {$query}
                set idHolder = 0,temperamentId = 0,temperamentName = {$db->quote($name)},temperamentData = {$db->quote($temperamentData)};
                select temperament.id into idHolder from temperaments_db as temperament where temperament._name = temperamentName limit 1;

                if idHolder then
                    set existsDb = concat(existsDb,if(existsDb = '[','',','),'{"name":"',temperamentName,'"}');
                else
                    start transaction;
                    insert into temperaments_db (_name,_added_by,_data) values (temperamentName,:userId,temperamentData);
                    set temperamentId = last_insert_id();
                    if temperamentId then
                        {$temperamentProfessionsQuery}
                        commit;
                        set totalSuccess = (totalSuccess + 1);
                    else
                        rollback;
                        set errorsDb = concat(errorsDb,if(errorsDb = '[','',','),'{"name":"',temperamentName,'"}');
                    end if;
                end if;
query;
        }

        $query = <<<"query"
            {$query}
            set existsDb = concat(existsDb,']'),errorsDb = concat(errorsDb,']');
            select concat('{"added":"',totalSuccess,'","exists":',existsDb,',"errors":',errorsDb,'}') as response;
            end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(true);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => array_merge($result,['invalids' => $invalids])
            ];
        }

        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeTemperaments(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateTemperaments')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare temperamentId bigint default 0;
                declare totalRemoved bigint default 0;
                declare totalError bigint default 0;
query;
        $temperamentIds = json_decode($request->get('temperamentIds'),true);
        if(!(is_array($temperamentIds) && count($temperamentIds))){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        foreach($temperamentIds as $temperamentId){
            $temperamentId = $this->getUtils()->getDataOfHash($temperamentId);
            $query .= <<<"query"
                set temperamentId = {$db->quote($temperamentId)};
                if (select 1 from temperaments_db where id = temperamentId) then
                    delete from temperaments_professions where _temperament_id = temperamentId;
                    delete from temperaments_db where id = temperamentId;
                    if row_count() then
                        set totalRemoved = (totalRemoved + 1);
                    else
                        set totalError = (totalError + 1);
                    end if;
                else
                    set totalError = (totalError + 1);
                end if;
query;
        }

        $query = <<<"query"
            {$query}
            select concat('{"totalRemoved":"',totalRemoved,'","totalError":"',totalError,'"}') as response;
            end;
query;

        $result = $db->query($query)->result(true);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => $result
            ];
        }

        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateTemperament(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateTemperaments')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/
        $professions = json_decode($request->get('professions'),true);
        if(!is_array($professions)){
            $professions = [];
        }

        $validatorRules = [
            'temperament_id' => 'required',
            'temperament_name' => 'required|min_len,3|max_len,255',
            'professions' => 'required|valid_array_size_greater,1',
            'temperament_description' => 'required|min_len,5|max_len,2000'
        ];

        $filterRules = [
            'temperament_name' => 'trim|sanitize_string|lower_case',
            'temperament_description' => 'trim|sanitize_string|lower_case'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run([
            'temperament_id' => $request->get('temperamentId'),
            'temperament_name' => $request->get('temperamentName'),
            'professions' => $professions,
            'temperament_description' => $request->get('temperamentDescription')
        ]);

        if($validatedData === false){
            $errors = $validator->get_errors_array();
            $errorText = '';
            foreach ($errors as $error) {
                $errorText .= $error.'; ';
            }
            return [
                'status' => 'error',
                'response' => $errorText
            ];
        }

        $temperamentId = $this->getUtils()->getDataOfHash($request->get('temperamentId'));
        $temperamentName = $this->getUtils()::getDataFromArray($validatedData,'temperament_name');
        $temperamentDescription = $this->getUtils()::getDataFromArray($validatedData,'temperament_description');
        $temperamentProfessionsQuery = 'set totalProfession = 0;';

        $temperamentData = json_encode([
            'description' => $temperamentDescription
        ]);

        foreach($professions as $key => $professionId){
            if(!\is_int($key)){
                continue;
            }
            $order = ((int) $key + 1);
            $professionId = $this->getUtils()->getDataOfHash($professionId);
            $temperamentProfessionsQuery .= <<<"query"
                set professionId = {$db->quote($professionId)};
                if (select 1 from professions_db where id = professionId) then
                    insert into temperaments_professions (_temperament_id,_profession_id,_order,_added_by) values (temperamentId,professionId,{$db->quote($order)},:userId);
                    if row_count() then
                        set totalProfession = (totalProfession + 1);
                    end if;
                end if;
query;
        }

        $query = <<<"query"
            begin not atomic
                declare temperamentName varchar(255) default {$db->quote($temperamentName)};
                declare temperamentData longtext;
                declare temperamentId bigint(255) default {$db->quote($temperamentId)};
                declare professionId bigint default 0;
                declare totalProfession bigint default 0;

                set temperamentData = {$db->quote($temperamentData)};

                if (select 1 from temperaments_db where id = temperamentId) then
                    start transaction;
                    if (select 1 from temperaments_db where ((_name = temperamentName) and (id != temperamentId)) limit 1) then
                        select '{"status":"error","response":"Temperament name already exists.. please update that record"}' as response;
                    else
                        update temperaments_db set _name = temperamentName,_data = temperamentData where id = temperamentId;
                        delete from temperaments_professions where _temperament_id = temperamentId;
                        {$temperamentProfessionsQuery}
                        commit;
                        select '{"status":"ok","response":"Updated succesfully"}' as response;
                    end if;
                else
                    select '{"status":"error","response":"Invalid temperament"}' as response;
                end if;
            end;
query;

        $result = $db->prepare($query)->bind([
            'userId' => $this->getSession()->get('userData-->id')
        ])->result(true);

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateAccountStatus(array $data){
        $forId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,'forId'));
        $for = $this->getUtils()::getDataFromArray($data,'for');
        $status = $this->getUtils()::getDataFromArray($data,'status');

        $db = $this->getDatabase();
        if(!($forId && is_numeric($forId) && is_string($for) && is_string($status))){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        if(!(isset(array_flip([
            'approve',
            'decline',
            'block',
            'unblock'
        ])[$status]) && isset(array_flip([
            'general-administrator',
            'support-administrator',
            'internship-provider-administrator',
            'institution-administrator',
            'secondary-school-administrator',
            'internship-provider',
            'institution',
            'secondary-school',
            'student'
        ])[$for]))){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $dbPointer = '';
        switch(true){
            case isset(array_flip([
                'general-administrator',
                'support-administrator'
            ])[$for]):
                $dbPointer = 'administrators';
            break;

            case isset(array_flip([
                'internship-provider',
                'internship-provider-administrator'
            ])[$for]):
                $dbPointer = 'internship_providers';
            break;

            case isset(array_flip([
                'institution',
                'institution-administrator'
            ])[$for]):
                $dbPointer = 'institutions';
            break;

            case isset(array_flip([
                'secondary-school',
                'secondary-school-administrator'
            ])[$for]):
                $dbPointer = 'secondary_schools';
            break;

            case ($for === 'student'):
                $dbPointer = 'students';
            break;
        }

        $data = [
            'action' => $status,
            'blockUser' => (((int) $this->getUtils()::getDataFromArray($data,'blockUser')) ?: 0),
            'forId' => &$forId,
            'for' => $for,
            'statusUpdaterType' => $this->getSession()->get('userData-->type'),
            'statusUpdaterUserId' => $this->getSession()->get('userData-->id'),
            'statusUpdaterAdminId' => 0,
            'statusUpdaterStakeholderId' => 0
        ];

        $statusUpdaterAdminData = $this->getSession()->get('adminData-->'.$data['statusUpdaterType']);
        if(is_array($statusUpdaterAdminData) && $statusUpdaterAdminData['isApproved'] && (!$statusUpdaterAdminData['isBlocked'])){
            $data['statusUpdaterAdminId'] = $statusUpdaterAdminData['id'];
            $data['statusUpdaterStakeholderId'] = $statusUpdaterAdminData['stakeholderId'];
        }

        $query = <<<"query"
            begin not atomic
            `inner_process`: begin
                declare statusUpdaterType varchar(255) default :statusUpdaterType;
                declare statusUpdaterUserId int default :statusUpdaterUserId;
                declare statusUpdaterAdminId int default :statusUpdaterAdminId;
                declare statusUpdaterStakeholderId int default :statusUpdaterStakeholderId;
                declare stakeholderId int default 0;
                declare blockUser tinyint default :blockUser;
                declare action varchar(255) default :action;
                declare userType varchar(255) default :for;
                declare forId int(255) default :forId;
                declare userId int default 0;
                declare adminId int default 0;
                declare idHolder int default 0;

                start transaction;
                if (action = 'decline') then
                    if (userType = 'student') then

                        set userId = 0,idHolder = 0;
                        select user.id,student._secondary_school_id into userId,idHolder from students as student join users as user on (student._user_id = user.id) where student.id = forId limit 1;

                        if not (userId and idHolder) then
                            rollback;
                            select '{"status":"error","response":"An error occured while processing student data"}' as response;
                            leave `inner_process`;
                        end if;

                        if (statusUpdaterType = 'secondary_school_administrator' and (statusUpdaterStakeholderId <> idHolder)) then
                            rollback;
                            select '{"status":"error","response":"Ooops.. you can only update student data for students in your school"}' as response;
                            leave `inner_process`;
                        end if;

                        update students set _is_approved = 0,_approved_date = now() where id = forId;

                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while declining request."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'general-administrator') or (userType = 'support-administrator')) then
                        set userId = 0;
                        select user.id into userId from administrators as admin join users as user on (admin._user_id = user.id) where admin.id = forId limit 1;
                        delete from administrators where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while declining request.."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'internship-provider-administrator') or (userType = 'institution-administrator') or (userType = 'secondary-school-administrator')) then
                        set userId = 0,stakeholderId = 0;
                        select user.id,stakeholder.id into userId,stakeholderId from administrators as admin left join {$dbPointer} as stakeholder on (admin._stakeholder_id = stakeholder.id) left join users as user on (admin._user_id = user.id) where admin.id = forId limit 1;
                        delete from administrators where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while declining request.."}' as response;
                            leave `inner_process`;
                        end if;

                        if stakeholderId then
                            if (select 1 from {$dbPointer} where _is_approved = 0 and isnull(_approved_date) and id = stakeholderId limit 1) then
                                delete from {$dbPointer} where id = stakeholderId;

                                if not row_count() then

                                    rollback;
                                    select '{"status":"error","response":"An error occured while declining request..."}' as response;
                                    leave `inner_process`;
                                end if;
                            end if;
                        end if;
                    end if;

                    if ((userType = 'internship-provider') or (userType = 'institution') or (userType = 'secondary-school')) then
                        set userId = 0,adminId = 0;
                        select user.id,admin.id into userId,adminId from {$dbPointer} as stakeholder left join administrators as admin on (admin._stakeholder_id = stakeholder.id) left join users as user on (admin._user_id = user.id) where stakeholder.id = forId limit 1;
                        delete from {$dbPointer} where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while declining request.."}' as response;
                            leave `inner_process`;
                        end if;

                        if adminId then
                            delete from administrators where id = adminId;

                            if not row_count() then

                                rollback;
                                select '{"status":"error","response":"An error occured while declining request..."}' as response;
                                leave `inner_process`;
                            end if;
                        end if;
                    end if;

                    update users set _is_approved = 0,_is_blocked = blockUser,_token = substr(unix_timestamp(),1,15) where id = userId;
                    if not row_count() then

                        if instr(userType,'administrator') then
                            rollback;
                            select '{"status":"error","response":"An error occured while declining request...."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    commit;
                    select '{"status":"ok","response":"Request decline succesfully"}' as response;
                    leave `inner_process`;
                end if;

                if (action = 'approve') then
                    if (userType = 'student') then

                        set userId = 0,idHolder = 0;
                        select user.id,student._secondary_school_id into userId,idHolder from students as student join users as user on (student._user_id = user.id) where student.id = forId limit 1;

                        if not (userId and idHolder) then
                            rollback;
                            select '{"status":"error","response":"An error occured while processing student data"}' as response;
                            leave `inner_process`;
                        end if;

                        if (statusUpdaterType = 'secondary_school_administrator' and (statusUpdaterStakeholderId <> idHolder)) then
                            rollback;
                            select '{"status":"error","response":"Ooops.. you can only update student data for students in your school"}' as response;
                            leave `inner_process`;
                        end if;

                        update students set _is_approved = 1,_approved_date = now() where id = forId;

                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while Approving request."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'general-administrator') or (userType = 'support-administrator')) then
                        set userId = 0;
                        select user.id into userId from administrators as admin left join users as user on (admin._user_id = user.id) where admin.id = forId limit 1;
                        update administrators set _is_approved = 1,_approved_date = now() where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while Approving request.."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'internship-provider-administrator') or (userType = 'institution-administrator') or (userType = 'secondary-school-administrator')) then
                        set userId = 0,stakeholderId = 0;
                        select user.id,stakeholder.id into userId,stakeholderId from administrators as admin left join {$dbPointer} as stakeholder on (admin._stakeholder_id = stakeholder.id) left join users as user on (admin._user_id = user.id) where admin.id = forId limit 1;
                        update administrators set _is_approved = 1,_approved_date = now() where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while Approving request.."}' as response;
                            leave `inner_process`;
                        end if;

                        if stakeholderId then
                            if (select 1 from {$dbPointer} where _is_approved = 0 and isnull(_approved_date) and id = stakeholderId limit 1) then
                                update {$dbPointer} set _is_approved = 1,_approved_date = now() where id = stakeholderId;

                                if not row_count() then

                                    rollback;
                                    select '{"status":"error","response":"An error occured while Approving request..."}' as response;
                                    leave `inner_process`;
                                end if;
                            end if;
                        end if;
                    end if;

                    if ((userType = 'internship-provider') or (userType = 'institution') or (userType = 'secondary-school')) then
                        set userId = 0,adminId = 0;
                        select user.id,admin.id into userId,adminId from {$dbPointer} as stakeholder left join administrators as admin on (stakeholder._added_by = admin.id) left join users as user on (admin._user_id = user.id) where stakeholder.id = forId limit 1;
                        update {$dbPointer} set _is_approved = 1,_approved_date = now() where id = forId;

                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while Approving request.."}' as response;
                            leave `inner_process`;
                        end if;

                        if adminId then
                            update administrators set _is_approved = 1,_approved_date = now() where id = adminId;

                            if not row_count() then

                                rollback;
                                select '{"status":"error","response":"An error occured while Approving request..."}' as response;
                                leave `inner_process`;
                            end if;
                        end if;
                    end if;

                    update users set _is_approved = 1,_is_blocked = 0,_token = substr(unix_timestamp(),1,15) where id = userId;
                    if not row_count() then

                        if instr(userType,'administrator') then
                            rollback;
                            select '{"status":"error","response":"An error occured while Approving request...."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    commit;
                    select '{"status":"ok","response":"Approval request succesful"}' as response;
                    leave `inner_process`;
                end if;

                if (action = 'block') then
                    if (userType = 'student') then

                        set userId = 0,idHolder = 0;
                        select user.id,student._secondary_school_id into userId,idHolder from students as student join users as user on (student._user_id = user.id) where student.id = forId limit 1;

                        if not (userId and idHolder) then
                            rollback;
                            select '{"status":"error","response":"An error occured while processing student data"}' as response;
                            leave `inner_process`;
                        end if;

                        if (statusUpdaterType = 'secondary_school_administrator' and (statusUpdaterStakeholderId <> idHolder)) then
                            rollback;
                            select '{"status":"error","response":"Ooops.. you can only update student data for students in your school"}' as response;
                            leave `inner_process`;
                        end if;

                        update students set _is_blocked = 1 where id = forId;

                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'general-administrator') or (userType = 'support-administrator') or (userType = 'internship-provider-administrator') or (userType = 'institution-administrator') or (userType = 'secondary-school-administrator')) then
                        update administrators set _is_blocked = 1 where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured.."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'internship-provider') or (userType = 'institution') or (userType = 'secondary-school')) then
                        update {$dbPointer} set _is_blocked = 1 where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured while Approving request.."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    commit;
                    select '{"status":"ok","response":"Block request was successful"}' as response;
                    leave `inner_process`;
                end if;

                if (action = 'unblock') then
                    if (userType = 'student') then

                        set userId = 0,idHolder = 0;
                        select user.id,student._secondary_school_id into userId,idHolder from students as student join users as user on (student._user_id = user.id) where student.id = forId limit 1;

                        if not (userId and idHolder) then
                            rollback;
                            select '{"status":"error","response":"An error occured while processing student data"}' as response;
                            leave `inner_process`;
                        end if;

                        if (statusUpdaterType = 'secondary_school_administrator' and (statusUpdaterStakeholderId <> idHolder)) then
                            rollback;
                            select '{"status":"error","response":"Ooops.. you can only update student data for students in your school"}' as response;
                            leave `inner_process`;
                        end if;

                        update students set _is_blocked = 0 where id = forId;

                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'general-administrator') or (userType = 'support-administrator') or (userType = 'internship-provider-administrator') or (userType = 'institution-administrator') or (userType = 'secondary-school-administrator')) then
                        update administrators set _is_blocked = 0 where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured..."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    if ((userType = 'internship-provider') or (userType = 'institution') or (userType = 'secondary-school')) then
                        update {$dbPointer} set _is_blocked = 0 where id = forId;
                        if not row_count() then

                            rollback;
                            select '{"status":"error","response":"An error occured.."}' as response;
                            leave `inner_process`;
                        end if;
                    end if;

                    commit;
                    select '{"status":"ok","response":"Unblock request was successful"}' as response;
                    leave `inner_process`;
                end if;
            end;
        end;
query;

        $result = $db->prepare($query)->bind($data)->result(false);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(is_array($result)){
                return $result;
            }
            return $this->getUtils()::getResponseFor('malformed-db-response');
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addQuestion(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateQuestions')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                inner_process: begin
                    declare totalQuestionsAdded bigint default 0;
                    declare sectionId bigint default 0;

                    start transaction;
query;
        $formData = $request->get('formData');
        $invalids = [];
        if(!is_array($formData)){
            return $this->getUtils()->getResponseFor('invalid-request');
        }

        foreach($formData as $key => &$question){
            $question = json_decode($question,true);
            if(!is_array($question)){
                $question = [];
            }

            $question = $validator->sanitize($question);
            $validatorRules = [
                'text' => 'required|min_len,3|max_len,255',
                'sectionId' => 'required',
                'options' => 'required|valid_array_size_greater,1'
            ];

            $filterRules = [
                'text' => 'trim|sanitize_string|lower_case',
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run($question);

            if($validatedData === false){
                $err = $validator->get_errors_array();
                $out = '';
                foreach($err as $errName => $errValue){
                    if($errName === 'options'){
                        $out .= 'Options must be more than one; ';
                    }else{
                        $out .= $errValue.'; ';
                    }
                }
                $invalids[] = [
                    'text' => $question['text'],
                    'errorText' => $out
                ];
                continue;
            }

            $text = $this->getUtils()::getDataFromArray($validatedData,'text');
            $sectionId = (int) $this->getUtils()->getDataOfHash($question['sectionId']);
            $options = $question['options'];
            $optionsDataContainer = [];

            foreach($options as $option){
                $validatorRules = [
                    'text' => 'required|min_len,3|max_len,50',
                    'score' => 'required|numeric',
                ];

                $filterRules = [
                    'text' => 'trim|sanitize_string|lower_case',
                    'score' => 'trim|sanitize_numbers'
                ];

                $validator->validation_rules($validatorRules);
                $validator->filter_rules($filterRules);
                $optionsData = $validator->run($option);

                $optionsDataContainer[] = $optionsData;
            }

            if(!count($optionsDataContainer)){
                $invalids[] = [
                    'text' => $text,
                    'errorText' => 'all added question options was invalid...'
                ];
                continue;
            }

            $data = json_encode([
                'text' => $text,
                'options' => $optionsDataContainer
            ]);

            $query .= <<<"query"
                    `inner_process_{$key}`: begin
                        set sectionId = {$db->quote($sectionId)};
                        if (select 1 from question_sections where id = sectionId) then
                            insert into questions(_section_id,_data) values (sectionId,{$db->quote($data)});

                            if last_insert_id() then
                                set totalQuestionsAdded = (totalQuestionsAdded + 1);
                                leave `inner_process_{$key}`;
                            end if;
                        end if;
                    end;
query;
        }

        $query .= <<<"query"

                    commit;
                    select concat('{"totalQuestionsAdded":"',totalQuestionsAdded,'"}') as response;

                end;
            end;
query;

        $result = $db->query($query)->result();
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            return [
                'status' => 'ok',
                'result' => array_merge($result,['invalids' => $invalids])
            ];
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeQuestions(){
        $request = $this->getUtils()->init('General-Request');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateQuestions')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/

        $query = <<<'query'
            begin not atomic
                declare questionId bigint default 0;
                declare totalRemoved bigint default 0;
                declare totalError bigint default 0;
query;
        $questionIds = json_decode($request->get('questionIds'),true);
        if(!is_array($questionIds) || !count($questionIds)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        foreach($questionIds as $questionId){
            $questionId = $this->getUtils()->getDataOfHash($questionId);

            $query .= <<<"query"
                set questionId = {$db->quote($questionId)};
                if (select 1 from questions where id = questionId) then
                    delete from questions where id = questionId;
                    if row_count() then
                        set totalRemoved = (totalRemoved + 1);
                    else
                        set totalError = (totalError + 1);
                    end if;
                else
                    set totalError = (totalError + 1);
                end if;
query;
        }

        $query .= <<<"query"
            select concat('{"totalRemoved":"',totalRemoved,'","totalError":"',totalError,'"}') as response;
            end;
query;

        $result = $db->query($query)->result(true);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return [
                'status' => 'ok',
                'result' => $result
            ];
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateQuestion(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        /*
        $googleCaptchaToken = $request->get('googleCaptchaToken');
        if(!is_string($googleCaptchaToken)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $googleCaptcha = $this->getUtils()->init('General-Google-Captcha');
        $googleCaptchaCheck = $googleCaptcha->process($googleCaptchaToken)->forCaptcha('v3')->forAction('adminUpdateQuestions')->verify();

        if($googleCaptchaCheck['status'] == 'error'){
            return $this->getUtils()::getResponseFor('invalid-request');
        }*/
        $question = $request->get('question');
        $question = json_decode($question,true);
        if(!is_array($professions)){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $validatorRules = [
            'question_id' => 'required',
            'section_id' => 'required',
            'question_text' => 'required|min_len,3|max_len,255',
            'options' => 'required|valid_array_size_greater,1'
        ];

        $filterRules = [
            'question_text' => 'trim|sanitize_string|lower_case'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run([
            'question_id' => $this->getUtils()::getDataFromArray($question,'id'),
            'section_id' => $this->getUtils()::getDataFromArray($question,'sectionId'),
            'question_text' => $this->getUtils()::getDataFromArray($question,'text'),
            'options' => $this->getUtils()::getDataFromArray($question,'options')
        ]);

        if($validatedData === false){
            $errors = $validator->get_errors_array();
            $errorText = '';
            foreach ($errors as $error) {
                $errorText .= $error.'; ';
            }
            return [
                'status' => 'error',
                'response' => $errorText
            ];
        }

        $questionId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($question,'id'));
        $sectionId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($question,'sectionId'));
        $question = $this->getUtils()::getDataFromArray($validatedData,'question_text');
        $options = $this->getUtils()::getDataFromArray($question,'options');
        $optionsDataContainer = [];
        foreach($options as $option){
            $validatorRules = [
                'text' => 'required|min_len,3|max_len,50',
                'score' => 'required|numeric',
            ];

            $filterRules = [
                'text' => 'trim|sanitize_string|lower_case',
                'score' => 'trim|sanitize_numbers'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $optionsData = $validator->run($option);

            $optionsDataContainer[] = $optionsData;
        }

        if(!count($optionsDataContainer)){
            return [
                'status' => 'error',
                'errorText' => 'all added question options was invalid...'
            ];
        }

        $data = json_encode([
            'text' => $name,
            'options' => $optionsDataContainer
        ]);

        $query .= <<<"query"
            begin not atomic
                declare sectionId bigint default 0;
                declare questionId bigint default 0;

                `inner_process`: begin
                    set sectionId = {$db->quote($sectionId)};
                    set questionId = {$db->quote($questionId)};
                    if (select 1 from question_sections as sectionDb join questions as question on (question._section_id = sectionDb.id) where sectionDb.id = sectionId and question.id = questionId) then
                        update questions set (_section_id = sectionId,_data = {$db->quote($data)}) where id = questionId;

                        if row_count() then
                            select '{"status":"ok","response":"Questions updated succesfully"}' as response;
                            leave `inner_process`;
                        end if;

                        select '{"status":"error","response":"Ooops update request failed"}' as response;
                        leave `inner_process`;
                    end if;
                end;
            end;
query;

        $result = $db->query($query)->result(true);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addEvent(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $eventData = $request->get('eventData');
        if(!is_array($eventData)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $uploader = $this->getUtils()->init('General-Uploader');
        $uploader->beginWatch();

        $totalFailed = 0;
        $totalExists = 0;
        $totalAdded = 0;

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
                    declare totalAdded int default 0;
                    declare totalExists int default 0;
                    declare totalFailed int default 0;
                    declare coverImages longtext;
                    declare eventName varchar(255);
                    declare eventDate timestamp;
                    declare eventLocation varchar(255);
                    declare eventDescription varchar(255);
                    declare eventCoverImage longtext;
                    declare eventData longtext;

                    set coverImages = '[';
query;

        foreach($eventData as $key => $event){

            $event = \json_decode($event,true);
            if(!is_array($event)){
                $totalFailed++;
                continue;
            }

            $validatorRules = [
                'name' => 'required|min_len,3|max_len,255',
                'location' => 'required|min_len,3|max_len,255',
                'description' => 'required|min_len,10|max_len,255'
            ];

            $filterRules = [
                'name' => 'trim|sanitize_string|lower_case',
                'location' => 'trim|sanitize_string|lower_case',
                'description' => 'trim|sanitize_string'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run($event);

            if($validatedData === false){
                $totalFailed++;
                continue;
            }

            if(($date = strtotime($event['date'] ?? 'bad')) === false){
                $totalFailed++;
                continue;
            }

            $coverImage = $uploader->getFileDataFor("eventCoverData-->{$key}-->tmp_name");
            if(!$coverImage){
                $totalFailed++;
                continue;
            }

            $finfo = \finfo_open(FILEINFO_MIME_TYPE);
            $mime = \finfo_file($finfo,$coverImage);

            if(!preg_match('#^image\/.*$#',$mime)){
                $totalFailed++;
                continue;
            }

            $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');

            if(!$mainCoverImage){
                unlink($coverImage);
                $totalFailed++;
                continue;
            }

            $name = $db->quote($validatedData['name']);
            $location = $db->quote($validatedData['location']);
            $description = $db->quote($validatedData['description']);
            $date = $db->quote(date('Y-m-d H:i:s',$date));
            $date = "str_to_date({$date},'%Y-%m-%d %H:%i:%s')";
            $coverImage = $db->quote($mainCoverImage);
            $addedInput = $event['addedInput'];
            $data = $db->quote(json_encode([
                'addedInputs' => $addedInput
            ]));

            $query .= <<<"query"
                set eventName = {$name},eventLocation = {$location},eventDate = {$date},eventDescription = {$description},eventCoverImage = {$coverImage},eventData = {$data};

                if (select 1 from events where _name = eventName and _location = eventLocation and _action_date = eventDate) then
                    set coverImages = concat(coverImages,if(coverImages = '[','',','),'"',eventCoverImage,'"');
                    set totalExists = (totalExists + 1);
                else
                    insert into events (_name,_description,_location,_added_by,_action_date,_cover_image,_data) values (eventName,eventDescription,eventLocation,1,eventDate,eventCoverImage,eventData);
                    if last_insert_id() then
                        set totalAdded = (totalAdded + 1);
                    else
                        set coverImages = concat(coverImages,if(coverImages = '[','',','),'"',eventCoverImage,'"');
                        set totalFailed = (totalFailed + 1);
                    end if;
                end if;
query;
        }

        $query .= <<<'query'
            select concat('{"totalAdded":',totalAdded,',"totalExists":',totalExists,',"totalFailed":',totalFailed,',"coverImages":',concat(coverImages,']'),'}') as response;
            end;
        end;
query;

        $result = $db->query($query)->result();

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            $result['totalAdded'] += $totalAdded;
            $result['totalFailed'] += $totalFailed;
            $result['totalExists'] += $totalExists;

            $coverImages = json_decode($result['totalExists'],true);
            if(is_array($coverImages) && count($coverImages)){
                foreach($coverImages as $coverImage){
                    unlink($coverImage);
                }
            }
            $result['coverImages'] = null;

            return [
                'status' => 'ok',
                'response' => $result
            ];
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateEvent(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $eventData = json_decode($request->get('eventData'),true);
        if(!is_array($eventData)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $validatorRules = [
            'id' => 'required',
            'name' => 'required|min_len,3|max_len,255',
            'location' => 'required|min_len,3|max_len,255',
            'description' => 'required|min_len,10|max_len,255'
        ];

        $filterRules = [
            'name' => 'trim|sanitize_string|lower_case',
            'location' => 'trim|sanitize_string|lower_case',
            'description' => 'trim|sanitize_string'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($eventData);

        if($validatedData === false){
            return [
                'status' => 'error',
                'response' => 'Invalid data'
            ];
        }

        if(($date = strtotime($eventData['date'] ?? 'bad')) === false){
            return [
                'status' => 'error',
                'response' => 'Invalid event date'
            ];
        }

        $uploader = $this->getUtils()->init('General-Uploader');
        $uploader->beginWatch();

        $coverImage = $uploader->getFileDataFor("eventCoverData-->tmp_name");

        $finfo = \finfo_open(FILEINFO_MIME_TYPE);
        $mime = \finfo_file($finfo,$coverImage);

        if(!($coverImage && preg_match('#^image\/.*$#',$mime))){
            return [
                'status' => 'error',
                'response' => 'Inavlid cover image'
            ];
        }

        $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');

        if(!$mainCoverImage){
            unlink($coverImage);
            return [
                'status' => 'error',
                'response' => 'An error occured while processing cover image'
            ];
        }

        $id = $db->quote($this->getUtils()->getDataOfHash($validatedData['id']));
        $name = $db->quote($validatedData['name']);
        $location = $db->quote($validatedData['location']);
        $description = $db->quote($validatedData['description']);
        $date = $db->quote(date('Y-m-d H:i:s',$date));
        $date = "str_to_date({$date},'%Y-%m-%d %H:%i:%s')";
        $coverImage = $db->quote($mainCoverImage);
        $addedInput = $eventData['addedInput'];
        $data = $db->quote(json_encode([
            'addedInputs' => $addedInput
        ]));

        $query = <<<"query"
            begin not atomic
                `inner_process`: begin
                    declare eventId int(255);
                    declare eventName varchar(255);
                    declare eventDate timestamp;
                    declare eventLocation varchar(255);
                    declare eventDescription varchar(255);
                    declare eventCoverImage longtext;
                    declare eventData longtext;
                    declare prevCoverImage longtext;

                    set eventId = {$id},eventName = {$name},eventLocation = {$location},eventDate = {$date},eventDescription = {$description},eventCoverImage = {$coverImage},eventData = {$data};

                    select _cover_image into prevCoverImage from events where id = eventId;
                    if not char_length(prevCoverImage) then
                        select '{"status":"error","response":"Ooops an error occured"}' as response;
                        leave `inner_process`;
                    end if;

                    if (select 1 from events where _name = eventName and _location = eventLocation and _action_date = eventDate and (not id = eventId)) then
                        select '{"status":"error","response":"Event already exists... Try updating previous event"}' as response;
                        leave `inner_process`;
                    end if;

                    update events set _name = eventName,_description = eventDescription,_location = eventLocation,_action_date = eventDate,_cover_image = eventCoverImage,_data = eventData where id = eventId;

                    if not row_count() then
                        select '{"status":"error","response":"Ooops an error occured"}' as response;
                        leave `inner_process`;
                    end if;

                    select concat('{"status":"ok","previousCoverImage":"',prevCoverImage,'"}') as response;
                end;
            end;
query;

        $result = $db->query($query)->result();
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            if($result['status'] == 'ok'){
                unlink($result['previousCoverImage']);
                $result['response'] = 'Event updated succesfully';
            }else{
                unlink($mainCoverImage);
            }

            return $result;
        }

        unlink($mainCoverImage);
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeEvent(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $eventIds = json_decode($request->get('eventIds'));
        if(!is_array($eventIds)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
query;

        foreach($eventIds as $eventId){
            $eventId = $db->quote($this->getUtils()->getDataOfHash($eventId));
            $query .= <<<"query"
                delete from events where id = {$eventId};
query;
        }

        $query .= <<<'query'
            select '{"status":"ok","response":"Event Removed succesfully"}' as response;
            end;
        end;
query;

        $result = $db->query($query)->result();
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addFaq($data){
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $data = Json_decode($data,true);
        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $totalFailed = 0;
        $totalExists = 0;
        $totalAdded = 0;

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
                    declare totalAdded int default 0;
                    declare totalExists int default 0;
                    declare totalFailed int default 0;
                    declare faqQuestion varchar(255);
                    declare faqAnswer longtext;
query;

        foreach($data as $faq){
            if(!is_array($faq)){
                $totalFailed++;
                continue;
            }

            $validatorRules = [
                'question' => 'required|min_len,3|max_len,255',
                'answer' => 'required|min_len,3|max_len,1000'
            ];

            $filterRules = [
                'question' => 'trim|sanitize_string|lower_case',
                'answer' => 'trim|sanitize_string|lower_case'
            ];

            $validator->validation_rules($validatorRules);
            $validator->filter_rules($filterRules);
            $validatedData = $validator->run($faq);

            if($validatedData === false){
                $totalFailed++;
                continue;
            }

            $question = $db->quote($validatedData['question']);
            $answer = $db->quote($validatedData['answer']);

            $query .= <<<"query"
                set faqQuestion = {$question},faqAnswer = {$answer};

                if (select 1 from faqs where _question = faqQuestion) then
                    set totalExists = (totalExists + 1);
                else
                    insert into faqs (_question,_answer) values (faqQuestion,faqAnswer);
                    if last_insert_id() then
                        set totalAdded = (totalAdded + 1);
                    else
                        set totalFailed = (totalFailed + 1);
                    end if;
                end if;
query;
        }

        $query .= <<<'query'
            select concat('{"totalAdded":',totalAdded,',"totalExists":',totalExists,',"totalFailed":',totalFailed,'}') as response;
            end;
        end;
query;

        $result = $db->query($query)->result();
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }

            $result['totalAdded'] += $totalAdded;
            $result['totalFailed'] += $totalFailed;
            $result['totalExists'] += $totalExists;

            return [
                'status' => 'ok',
                'response' => $result
            ];
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateFaq($data){
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $data = json_decode($data,true);
        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $validatorRules = [
            'question' => 'required|min_len,3|max_len,255',
            'answer' => 'required|min_len,3|max_len,1000',
        ];

        $filterRules = [
            'question' => 'trim|sanitize_string|lower_case',
            'answer' => 'trim|sanitize_string|lower_case',
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($data);

        if($validatedData === false){
            return [
                'status' => 'error',
                'response' => 'Invalid data'
            ];
        }

        $id = $db->quote($this->getUtils()->getDataOfHash($validatedData['id']));
        $question = $db->quote($validatedData['question']);
        $answer = $db->quote($validatedData['answer']);

        $query = <<<"query"
            begin not atomic
                `inner_process`: begin
                    declare faqId int(255);
                    declare faqQuestion varchar(255);
                    declare faqAnswer longtext;

                    set faqId = {$id},faqQuestion = {$question},faqAnswer = {$answer};

                    if (select 1 from faqs where _question = faqQuestion and id != faqId) then
                        select '{"status":"error","response":"Faq already exists"}' as response;
                        leave `inner_process`;
                    end if;

                    update faqs set _question = faqQuestion,_answer = faqAnswer where id = faqId;

                    if not row_count() then
                        select '{"status":"error","response":"Ooops an error occured"}' as response;
                        leave `inner_process`;
                    end if;

                    select concat('{"status":"ok","response":"Faq updated succesfully"}') as response;
                end;
            end;
query;

        $result = $db->query($query)->result();
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeFaq($data){
        $db = $this->getDatabase();

        $data = json_decode($data,true);
        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
query;

        foreach($data as $faqId){
            $faqId = $db->quote($this->getUtils()->getDataOfHash($faqId));
            $query .= <<<"query"
                delete from faqs where id = {$faqId};
query;
        }

        $query .= <<<'query'
            select '{"status":"ok","response":"Faq Removed succesfully"}' as response;
            end;
        end;
query;

        $result = $db->query($query)->result();
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addTicket($data){
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $data = json_decode($data,true);
        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $type = $this->getUtils()::getDataFromArray($data,'type');

        if(!isset(array_flip([
            'response',
            'request'
        ])[$type])){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $validatorRules = [
            'from' => 'required',
            'fromId' => 'required',
            'message' => 'required|min_len,10|max_len,2000'
        ];

        $filterRules = [
            'message' => 'trim|sanitize_string|lower_case',
        ];

        if($type === 'request'){
            $validatorRules['title'] = 'required|min_len,5|max_len,255';
            $validatorRules['category'] = 'required';
            $validatorRules['priority'] = 'required';
            $filterRules['title'] = 'trim|sanitize_string|lower_case';
        }else{
            $validatorRules['forId'] = 'required';
        }

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($data);

        if($validatedData === false){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $from = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'from'));
        $fromId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'fromId'));
        $title = $this->getUtils()->getDataFromArray($validatedData,'title');
        $message = $this->getUtils()->getDataFromArray($validatedData,'message');
        $categoryId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'category')) ?: 0;
        $priorityId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'priority')) ?: 0;
        $forId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'forId')) ?: 0;
        $ticketData = ($type === 'request' ? [
            'timeline' => [
                [
                    'action' => 'open',
                    'by' => [
                        'display_type' => ucwords(str_replace('-',' ',$this->getSession()->get('userData-->type'))),
                        'type' => $this->getSession()->get('userData-->type'),
                        'userId' => $this->getSession()->get('userData-->id'),
                        'adminId' => $this->getSession()->get("adminData-->{$this->getSession()->get('userData-->type')}-->id"),
                        'studentId' => $this->getSession()->get('studentData-->id')
                    ],
                    'date' => microtime(true)
                ]
            ]
        ] : null);

        if(!(isset(array_flip([
            'general-administrator',
            'support-administrator',
            'institution-administrator',
            'secondary-school-administrator',
            'internship-provider-administrator',
            'student'
        ])[$from]) && is_numeric($fromId))){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
                    declare __type varchar(255);
                    declare __idHolder int(255);
                    declare __title varchar(255);
                    declare __message longtext;
                    declare __from varchar(255);
                    declare __fromId int(255);
                    declare __categoryId int(255);
                    declare __ticketId int(255);
                    declare __data longtext;

                    set __type = :type,__idHolder = 0,__title = :title,__message = :message,__from = :from,__fromId = :fromId,__categoryId = :categoryId,__ticketId = :forId,__data = :data;

                    if (__type = 'request') then

                        if (__categoryId and (not (select 1 from tickets_categories_db where id = __categoryId and _is_approved = 1))) then
                            select '{"status":"error","response":"Invalid ticket request"}' as response;
                            leave `inner_process`;
                        end if;

                        set __idHolder = 0;
                        select 1 into __idHolder from tickets where _title = __title and _from = __from and _from_id = __fromId;
                        if __idHolder then
                            select '{"status":"error","response":"ticket with similar issue already exists... please refer to the ticket"}' as response;
                            leave `inner_process`;
                        end if;

                        insert into tickets (_title,_message,_from,_from_id,_ticket_category_id,_data) values (__title,__message,__from,__fromId,__categoryId,__data);
                        if not row_count() then
                            select '{"status":"error","response":"An error occured while adding ticket"}' as response;
                            leave `inner_process`;
                        end if;

                        select '{"status":"ok","response":"Ticket added succesfully"}' as response;
                        leave `inner_process`;

                    else

                        if (select 1 from tickets where _is_closed = 1 and id = __ticketId) then
                            select '{"status":"error","response":"Ooops ticket is closed... And as such no further reply"}' as response;
                            leave `inner_process`;
                        end if;

                        start transaction;
                        update tickets set _is_seen = 0 where id = __ticketId;
                        insert into tickets (_title,_message,_from,_from_id,_is_response,_ticket_id) values ('',__message,__from,__fromId,1,__ticketId);

                        if not row_count() then
                            rollback;
                            select '{"status":"error","response":"An error occured while replying ticket"}' as response;
                            leave `inner_process`;
                        end if;

                        commit;
                        select '{"status":"ok","response":"Ticket reply sent succesfully"}' as response;
                        leave `inner_process`;
                    end if;
                end;
            end;
query;

        $result = $db->prepare($query)->bind([
            'type' => &$type,
            'title' => &$title,
            'message' => &$message,
            'from' => &$from,
            'fromId' => &$fromId,
            'categoryId' => &$categoryId,
            'forId' => &$forId,
            'data' => json_encode($ticketData)
        ])->result();

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function updateTicket($data){
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $data = json_decode($data,true);
        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $type = $this->getUtils()::getDataFromArray($data,'type');
        if(!isset(array_flip([
            'ticket',
            'ticketData',
            'ticketResponse'
        ])[$type])){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $validatorRules = [
            'id' => 'required'
        ];

        $filterRules = [];

        switch($type){
            case 'ticket':
                $validatorRules['title'] = 'required|min_len,5|max_len,255';
                $validatorRules['message'] = 'required|min_len,20|max_len,2000';
                $filterRules['title'] = 'trim|sanitize_string|lower_case';
                $filterRules['message'] = 'trim|sanitize_string|lower_case';
                $validatorRules['category'] = 'required';
                $validatorRules['priority'] = 'required';
            break;

            case 'ticketData':
                $validatorRules['instructions'] = 'required|valid_array_size_greater,1';
            break;

            case 'ticketResponse':
                $validatorRules['message'] = 'required|min_len,20|max_len,2000';
                $filterRules['message'] = 'trim|sanitize_string|lower_case';
            break;
        }

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($data);

        if($validatedData === false){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $instructionData = '';
        $ticketId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'id'));
        $title = $this->getUtils()::getDataFromArray($validatedData,'title') ?: '';
        $message = $this->getUtils()::getDataFromArray($validatedData,'message') ?: '';
        $categoryId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'category')) ?: 0;
        $priorityId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'priority')) ?: 0;
        $instructions = $this->getUtils()::getDataFromArray($validatedData,'instructions') ?: [];
        $ticketData = null;

        if(!is_numeric($ticketId)){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        if(isset(array_flip([
            'ticket',
            'ticketData'
        ])[$type])){
            $ticketData = $db->prepare('select _is_seen as isSeen,_is_closed as isClosed,_data as data from tickets where id = :ticketId limit 1')->bind([
                'ticketId' => &$ticketId
            ])->result();

            $isSeen = null;
            $isClosed = null;

            if($ticketData){
                $isSeen = (int) $ticketData[0]['isSeen'];
                $isClosed = (int) $ticketData[0]['isClosed'];
                $ticketData = json_decode($ticketData[0]['data'],true);
            }

            if(!is_array($ticketData)){
                $ticketData = [];
            }

            switch($type){
                case 'ticket':
                    $this->getUtils()::setDataInArray($ticketData,'timeline[]',[
                        'action' => 'update',
                        'by' => [
                            'display_type' => ucwords(str_replace('-',' ',$this->getSession()->get('userData-->type'))),
                            'type' => $this->getSession()->get('userData-->type'),
                            'userId' => $this->getSession()->get('userData-->id'),
                            'adminId' => $this->getSession()->get("adminData-->{$this->getSession()->get('userData-->type')}-->id")
                        ],
                        'date' => microtime(true)
                    ]);
                break;

                case 'ticketData':

                    foreach($instructions as $instruction){
                        if(is_array($instruction) && isset(array_flip([
                            'close',
                            'reopen',
                            'seen'
                        ])[$instruction['key'] ?? false])){

                            switch($instruction['key']){
                                case 'close':
                                    if($isClosed !== 1){
                                        $value = 1;
                                        $prepend = $instructionData ? ',' : '';
                                        $instructionData .= "{$prepend}_is_closed = {$value}";
                                    }else{
                                        return [
                                            'status' => 'error',
                                            'response' => 'Ooops.. this ticket must be opened prior to being closed'
                                        ];
                                    }
                                break;

                                case 'reopen':
                                    if($isClosed === 1){
                                        $value = 0;
                                        $prepend = $instructionData ? ',' : '';
                                        $instructionData .= "{$prepend}_is_closed = {$value}";
                                    }else{
                                        return [
                                            'status' => 'error',
                                            'response' => 'Ooops.. this ticket must be closed prior to being re opened'
                                        ];
                                    }
                                break;

                                case 'seen':
                                    if($isSeen === 1){
                                        $value = 1;
                                        $prepend = $instructionData ? ',' : '';
                                        $instructionData .= "{$prepend}_is_seen = {$value}";
                                    }else{
                                        return [
                                            'status' => 'error',
                                            'response' => 'Ooops.. this ticket is already seen'
                                        ];
                                    }
                                break;
                            }

                            $this->getUtils()::setDataInArray($ticketData,'timeline[]',[
                                'action' => $instruction['key'],
                                'by' => [
                                    'display_type' => ucwords(str_replace('-',' ',$this->getSession()->get('userData-->type'))),
                                    'type' => $this->getSession()->get('userData-->type'),
                                    'userId' => $this->getSession()->get('userData-->id'),
                                    'adminId' => $this->getSession()->get("adminData-->{$this->getSession()->get('userData-->type')}-->id")
                                ],
                                'date' => microtime(true)
                            ]);
                        }
                    }
                break;
            }
        }

        if(!$instructionData){
            $instructionData = 'id = __ticketId';
        }else{
            $instructionData .= ',_data = __data';
        }

        $query = <<<"query"
            begin not atomic
                `inner_process`: begin
                    declare __idHolder int(255);
                    declare __title varchar(255);
                    declare __message longtext;
                    declare __ticketId varchar(255);
                    declare __categoryId int(255);
                    declare __type varchar(255);
                    declare __from varchar(255);
                    declare __fromId int(255);
                    declare __data longtext;

                    set __idHolder = 0, __title = :title, __message = :message,__ticketId = :ticketId,__categoryId = :categoryId,__type = :type,__data = :data;

                    select 1,_from,_from_id into __idHolder,__from,__fromId from tickets where id = __ticketId;
                    if (not __idHolder) then
                        select '{"status":"error","response":"Invalid ticket"}' as response;
                        leave `inner_process`;
                    end if;
                    set __idHolder = 0;

                    if (__type = 'ticket') then
                        select 1 into __idHolder from tickets where _is_response = 1 and _ticket_id = __ticketId;
                        if __idHolder then

                            select '{"status":"error","response":"Ticket meta data cannot be updated anymore"}' as response;
                            leave `inner_process`;
                        end if;

                        if (__categoryId and (not (select 1 from tickets_categories_db where id = __categoryId and _is_approved = 1))) then
                            select '{"status":"error","response":"Invalid ticket request"}' as response;
                            leave `inner_process`;
                        end if;

                        set __idHolder = 0;
                        select 1 into __idHolder from tickets where _title = __title and _from = __from and _from_id = __fromId and (id <> __ticketId);
                        if __idHolder then
                            select '{"status":"error","response":"ticket with similar issue already exists... please refer to the ticket"}' as response;
                            leave `inner_process`;
                        end if;

                        update tickets set _title = __title, _message = __message, _ticket_category_id = __categoryId where id = __ticketId;

                    end if;

                    if (__type = 'ticketData') then
                        update tickets set {$instructionData} where id = __ticketId;
                    end if;

                    if (__type = 'ticketResponse') then
                        update tickets set _message = __message where id = __ticketId;
                    end if;

                    if not row_count() then
                        select '{"status":"error","response":"An error occured while updating ticket"}' as response;
                        leave `inner_process`;
                    end if;

                    select '{"status":"ok","response":"Ticket updated succesfully"}' as response;
                    leave `inner_process`;
                end;
            end;
query;

        $result = $db->prepare($query)->bind([
            'title' => &$title,
            'message' => &$message,
            'ticketId' => &$ticketId,
            'categoryId' => &$categoryId,
            'type' => &$type,
            'data' => json_encode($ticketData)
        ])->result();

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function removeTicket($data){
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $data = json_decode($data,true);
        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $validatorRules = [
            'ticketId' => 'required'
        ];

        $filterRules = [];
        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($data);

        if($validatedData === false){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $ticketId = $this->getUtils()::getDataOfHash($this->getUtils()::getDataFromArray($validatedData,'ticketId'));

        if(!is_numeric($ticketId)){
            return $this->getUtils()::getResponseFor('parameters-error');
        }

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
                    declare __userId int(255);
                    declare __from varchar(255);
                    declare __fromId int(255);
                    declare __ticketId int(255);
                    declare __idHolder int(255) default 0;
                    declare __isSuper tinyint default 0;
                    declare __canRemove tinyint default 0;

                    set __ticketId = :ticketId,__isSuper = :isSuper,__userId = :userId;

                    select 1,_from,_from_id into __idHolder,__from,__fromId from tickets where id = __ticketId;
                    if (not __idHolder) then
                        select '{"status":"error","response":"Invalid ticket"}' as response;
                        leave `inner_process`;
                    end if;
                    set __idHolder = 0;

                    if not __isSuper then
                        if (__from = 'administrator') then
                            select 1 into __idHolder from administrators as admin join users as user on (admin._user_id = user.id) where user.id = __userId;
                        else
                            select 1 into __idHolder from students as student join users as user on (student._user_id = user.id) where user.id = __userId;
                        end if;

                        set __canRemove = 0;
                        if __idHolder then
                            set __canRemove = 1;
                        end if;
                    else
                        set __canRemove = 1;
                    end if;

                    if not __canRemove then
                        select '{"status":"error","response":"Ooops... you do not have the permission to remove this ticket"}' as response;
                        leave `inner_process`;
                    end if;

                    delete from tickets where id = __ticketId;

                    if not row_count() then
                        select '{"status":"error","response":"An error occured while removing ticket"}' as response;
                        leave `inner_process`;
                    end if;

                    select '{"status":"ok","response":"Ticket removed succesfully"}' as response;
                    leave `inner_process`;
                end;
            end;
query;

        $result = $db->query($query)->bind([
            'ticketId' => &$ticketId,
            'isSuper' => ((int) \in_array($this->getSession()->get('userData-->type'),[
                'general-administrator',
                'support-administrator'
            ],true)),
            'userId' => $this->getSession()->get('userData-->id')
        ])->result();

        if($result){
            $result = json_decode($result[0]['response'],true);
            if(!is_array($result)){
                return $this->getUtils()::getResponseFor('malformed-db-response');
            }
            return $result;
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function processTestResponse($data){
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $response = (int) $this->getUtils()::getDataFromArray($data,'response');
        $reply = (string) $this->getUtils()::getDataFromArray($data,'reply');

        switch($response){
            case 1:
                $response = 1;
            break;

            case 0:
                $validatorRules = [
                    'reply' => 'required|min_len,5|max_len,1000'
                ];

                $filterRules = [
                    'reply' => 'trim|sanitize_string|lower_case'
                ];

                $validator->validation_rules($validatorRules);
                $validator->filter_rules($filterRules);
                $validatedData = $validator->run([
                    'reply' => $reply
                ]);

                if($validatedData === false){
                    return [
                        'status' => 'error',
                        'response' => 'A brief explanation of the problem is required.'
                    ];
                }else{
                    $reply = $validatedData['reply'];
                }
            break;

            default:
                return $this->getUtils()::getResponseFor('parameters-error');
            break;
        }

        $ticketFrom = 'student';
        $ticketFromId = $this->getSession()->get('studentData-->id');
        $title = 'Student FeedBack Report For Test';

        $result = $db->prepare('
            begin not atomic
                `inner_process`: begin
                    declare isClosed tinyint default 0;
                    declare ticketData longtext;
                    declare ticketId int default 0;
                    declare __messageHolder longtext;

                    select ticket.id,ticket._is_closed,ifnull(ticket._data,\'{}\'),_message into ticketId,isClosed,ticketData,__messageHolder from tickets as ticket where ticket._from = :ticketFrom and ticket._from_id = :ticketFromId and ticket._title = :title limit 1;

                    select concat(\'{"id":"\',ifnull(ticketId,0),\'","isClosed":\',ifnull(isClosed,0),\',"exists":\',if(isnull(__messageHolder),0,1),\',"data":\',ifnull(ticketData,\'{}\'),\',"hasSameMessage":\',if((not isnull(__messageHolder)),if(lcase(__messageHolder) = lcase(:message),1,0),0),\'}\') as response;
                    leave `inner_process`;
                end;
            end;
        ')->bind([
            'ticketFrom' => $ticketFrom,
            'ticketFromId' => $ticketFromId,
            'title' => $title,
            'message' => $reply
        ])->result();

        $isClosed;
        $exists = 0;
        $ticketId = 0;
        $outData = [];

        if($result){
            $ticketData = json_decode($result[0]['response'],true);
            $isClosed = (int) $ticketData['isClosed'];
            $exists = $ticketData['exists'];
            $data = $ticketData['data'];
            $ticketId = $ticketData['id'];
            $hasSameMessage = $ticketData['hasSameMessage'];

            if($exists){
                if(is_array($data)){
                    $outData = $data;
                }

                if($hasSameMessage){
                    return [
                        'status' => 'error',
                        'response' => 'Ooops problem of this nature has been resolved before'
                    ];
                }

                if($isClosed){
                    $this->getUtils()::setDataInArray($outData,'timeline[]',[
                        'action' => 'reopen',
                        'by' => [
                            'display_type' => ucwords(str_replace('-',' ',$this->getSession()->get('userData-->type'))),
                            'type' => $this->getSession()->get('userData-->type'),
                            'userId' => $this->getSession()->get('userData-->id'),
                            'adminId' => $this->getSession()->get("adminData-->{$this->getSession()->get('userData-->type')}-->id")
                        ],
                        'date' => microtime(true)
                    ]);
                }
            }else{
                $this->getUtils()::setDataInArray($outData,'timeline[]',[
                    'action' => 'open',
                    'by' => [
                        'display_type' => ucwords(str_replace('-',' ',$this->getSession()->get('userData-->type'))),
                        'type' => $this->getSession()->get('userData-->type'),
                        'userId' => $this->getSession()->get('userData-->id'),
                        'adminId' => $this->getSession()->get("adminData-->{$this->getSession()->get('userData-->type')}-->id")
                    ],
                    'date' => microtime(true)
                ]);
            }

            $result = $db->prepare('
                begin not atomic
                    `inner_process`: begin
                        declare ticketCategoryId int default 0;
                        declare ticketFrom varchar(255) default :ticketFrom;
                        declare ticketFromId int default :ticketFromId;
                        declare ticketTitle varchar(255) default :ticketTitle;
                        declare ticketMessage longtext;
                        declare ticketData longtext;
                        declare ticketId int default :ticketId;

                        set ticketMessage = :message,ticketData = :ticketData;
                        insert into test_survey_response_stats (_student_id,_yes,_no) values (ticketFromId,:yes,:no);

                        if (not row_count()) then
                            select \'{"status":"error","response":"An error occured while submitting response... please try again later"}\' as response;
                            leave `inner_process`;
                        end if;

                        if :no then
                            if (:ticketExists and ticketId) then
                                update tickets set _is_seen = 0,_is_closed = 0,_data = ticketData where id = ticketId;
                                insert into tickets(_title,_message,_from,_from_id,_is_response,_ticket_id) values (\'\',ticketMessage,ticketFrom,ticketFromId,1,ticketId);

                                if (not row_count()) then
                                    select \'{"status":"error","response":"An underlying straam interruption occured. please try again later."}\' as response;
                                    leave `inner_process`;
                                end if;
                            else
                                select category.id into ticketCategoryId from tickets_priorities_db as priority join tickets_categories_db as category on (priority.id = category._ticket_priority_id and priority._name = \'test feedback\') limit 1;

                                if (not ticketCategoryId) then
                                    select \'{"status":"error","response":"Report could not be submitted for now... please try again later"}\' as response;
                                    leave `inner_process`;
                                end if;

                                insert into tickets(_title,_message,_from,_from_id,_ticket_category_id,_data) values (ticketTitle,ticketMessage,ticketFrom,ticketFromId,ticketCategoryId,ticketData);

                                if (not row_count()) then
                                    select \'{"status":"error","response":"An underlying stream interruption occured. please try again later.."}\' as response;
                                    leave `inner_process`;
                                end if;
                            end if;
                        end if;

                        select \'{"status":"ok","response":"Response submitted succesfully"}\' as response;
                        leave `inner_process`;
                    end;
                end;
            ')->bind([
                'yes' => ($response ? 1 : 0),
                'no' => ($response ? 0 : 1),
                'ticketTitle' => $title,
                'message' => $reply,
                'ticketFrom' => $ticketFrom,
                'ticketFromId' => $ticketFromId,
                'ticketExists' => $exists,
                'ticketId' => $ticketId,
                'ticketData' => json_encode($outData)
            ])->result();

            if($result){
                return json_decode($result[0]['response'],true);
            }

            return [
                'status' => 'error',
                'response' => 'A data center error occured.'
            ];
        }

        return [
            'status' => 'error',
            'response' => 'A data center error occured'
        ];
    }

    public function updateUserPortfolio($data){
        $db = $this->getDatabase();
        $validator = $this->getUtils()->init('General-Validator');
        $uploader = $this->getUtils()->init('General-Uploader');
        $uploader->beginWatch();

        $data = json_decode($data,true);

        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $controllerType = $this->getUtils()->getDataOfHash($data['controllerType']);
        $basicCheckList = [
            'id',
            'name',
            'country',
            'region',
            'lga',
            'stateOfResidence',
            'image',
            'student' => [
                'id',
                'dob'
            ]
        ];

        $councellorCheckList = [
            'username',
            'email',
            'phoneNumber',
            'isApproved',
            'isBlocked',
            'isVerified',
            'password',
            'confirmPassword',
            'student' => [
                'class',
                'school' => [
                    'id',
                    'name',
                    'uniqueName',
                    'address',
                    'country',
                    'lga',
                    'region',
                    'description',
                    'image'
                ]
            ]
        ];

        $all = [
            'student' => [
                'school' => [
                    'isApproved',
                    'isBlocked'
                ]
            ],
            'admin' => [
                'id',
                'isApproved',
                'isBlocked',
                'stakeholder' => [
                    'id',
                    'name',
                    'uniqueName',
                    'address',
                    'country',
                    'lga',
                    'region',
                    'description',
                    'isApproved',
                    'isBlocked',
                    'image'
                ]
            ]
        ];

        $query = <<<"query"
            begin not atomic
                `inner_process`: begin
                    declare pointerId int;
                    declare errors longtext;
                    declare isValidId tinyint default 0;
                    declare hasUpdate tinyint default 0;

                    set errors = '[';
query;

        $checkList;
        $userManager = $this->getUtils()->init('Users-Manager');

        switch($controllerType){
            case 'all':
                if(!$userManager->hasPermissionAs('general-administrator')){
                    return [
                        'status' => 'error',
                        'response' => 'Invalid request...'
                    ];
                }
                $checkList = array_merge_recursive($basicCheckList,$councellorCheckList,$all);
            break;

            case 'school-all':
                if(!$userManager->hasPermissionAs('secondary-school-administrator')){
                    return [
                        'status' => 'error',
                        'response' => 'Invalid request....'
                    ];
                }
                $checkList = array_merge_recursive($basicCheckList,$councellorCheckList);
            break;

            case 'basic':
                $checkList = $basicCheckList;
            break;
        }

        $hasSetId = null;
        $id = 0;

        foreach($data as $key => $value){
            if(is_null($hasSetId)){
                $id = $this->getUtils()->getDataOfHash($data['id'] ?? '');

                if(is_numeric($id)){
                    $hasSetId = 1;
                    $item = $db->quote($id);
                    $query .= <<<"query"

                        case {$db->quote($controllerType)}
                            when 'all' then
                                if (select 1 from users where id = {$item}) then
                                    set pointerId = {$item},isValidId = 1;
                                else
                                    set pointerId = 0,isValidId = 0;
                                end if;
                            when 'school-all' then
                                if (select 1 from users as user join students as student on (user._type = 'student' and student._user_id = user.id) join secondary_schools as school on (student._secondary_school_id = school.id and school.id = {$db->quote($this->getSession()->get('adminData-->secondary-school-administrator-->stakeholderId'))}) where user.id = {$item}) then
                                    set pointerId = {$item},isValidId = 1;
                                else
                                    set pointerId = 0,isValidId = 0;
                                end if;
                            when 'basic' then
                                if (select 1 from users where id = {$item} and id = {$db->quote($this->getSession()->get('userData-->id'))}) then
                                    set pointerId = {$item},isValidId = 1;
                                else
                                    set pointerId = 0,isValidId = 0;
                                end if;
                        end case;

                        if not isValidId then
                            select '{"status":"error","response":"Invalid request....."}' as response;
                            leave `inner_process`;
                        end if;
query;
                    if(is_numeric(array_search('image',$checkList,true))){
                        $coverImage = $uploader->getFileDataFor("image-->tmp_name");
                        if($coverImage){
                            $finfo = \finfo_open(FILEINFO_MIME_TYPE);
                            $mime = \finfo_file($finfo,$coverImage);

                            if(preg_match('#^image\/.*$#',$mime)){
                                $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');
                                if($mainCoverImage){
                                    $result = $db->query("select _data as data from users where id = {$item} limit 1")->result();
                                    if($result){
                                        $previousImage = '';
                                        $result = json_decode($result[0]['data'],true);
                                        if(is_array($result)){
                                            $previousImage = $this->getUtils()::getDataFromArray($result,'cover-image');
                                        }else{
                                            $result = [];
                                        }

                                        $this->getUtils()::setDataInArray($result,'cover-image',$mainCoverImage);
                                        $result = $db->prepare(<<<"query"
                                            begin not atomic
                                                `inner_process`: begin
                                                    update users set _data = :data,_token = substr(unix_timestamp(),1,15) where id = {$item};
                                                end;
                                            end;
query
                                            )->bind([
                                                'data' => json_encode($result)
                                        ])->result(false);

                                        if($previousImage){
                                            @unlink($previousImage);
                                        }
                                    }
                                }else{
                                    unlink($coverImage);
                                }
                            }else{
                                unlink($coverImage);
                            }
                        }
                    }
                }else{
                    $hasSetId = 0;
                }
            }

            if(!is_array($value)){
                $exists = array_search($key,$checkList,true);

                if(is_numeric($exists)){
                    if(isset(array_flip([
                        'confirmPassword',
                        'id',
                        'image'
                    ])[$key]) || ($hasSetId === 0)){
                        continue;
                    }

                    switch($key){
                        case 'username':
                            $validatorRules = [
                                $key => 'min_len,3|max_len,255'
                            ];

                            $filterRules = [
                                $key => 'trim|sanitize_string'
                            ];

                            $validator->validation_rules($validatorRules);
                            $validator->filter_rules($filterRules);
                            $validatedData = $validator->run([
                                $key => $value
                            ]);

                            if($validatedData){
                                $item = $db->quote($validatedData[$key]);

                                $query .= <<<"query"
                                    if (select 1 from users where _unique_name = {$item}) then
                                        set errors = concat(errors,if(errors = '[','',','),'{"username":"Username already exists"}');
                                    else
                                        update users set _unique_name = {$item} where id = pointerId;

                                        if not row_count() then
                                            set errors = concat(errors,if(errors = '[','',','),'{"username":"An error occured while updating username"}');
                                        else
                                            set hasUpdate = 1;
                                        end if;
                                    end if;
query;
                            }
                        break;
                        case 'name':
                            $validatorRules = [
                                $key => 'required|min_len,5|max_len,150',
                            ];

                            $filterRules = [
                                $key => 'trim|sanitize_string|lower_case'
                            ];

                            $validator->validation_rules($validatorRules);
                            $validator->filter_rules($filterRules);
                            $validatedData = $validator->run([
                                $key => $value
                            ]);

                            if($validatedData){
                                $item = $db->quote($validatedData[$key]);

                                $query .= <<<"query"
                                    update users set _name = {$item} where id = pointerId;

                                    if not row_count() then
                                        set errors = concat(errors,if(errors = '[','',','),'{"name":"An error occured while updating name"}');
                                    else
                                        set hasUpdate = 1;
                                    end if;
query;
                            }
                        break;
                        case 'phoneNumber':
                            $validatorRules = [
                                $key => 'required|numeric|min_len,9|max_len,15'
                            ];

                            $filterRules = [
                                $key => 'trim|sanitize_numbers'
                            ];

                            $validator->validation_rules($validatorRules);
                            $validator->filter_rules($filterRules);
                            $validatedData = $validator->run([
                                $key => $value
                            ]);

                            if($validatedData){
                                $item = $db->quote($validatedData[$key]);

                                $query .= <<<"query"
                                    if (select 1 from users where _phone_number = {$item}) then
                                        set errors = concat(errors,if(errors = '[','',','),'{"{$key}":"Phone Number already exists"}');
                                    else
                                        update users set _phone_number = {$item} where id = pointerId;

                                        if not row_count() then
                                            set errors = concat(errors,if(errors = '[','',','),'{"{$key}":"An error occured while updating phone number"}');
                                        else
                                            set hasUpdate = 1;
                                        end if;
                                    end if;
query;
                            }
                        break;
                        case 'email':
                            $validatorRules = [
                                $key => 'min_len,3|max_len,255|valid_email'
                            ];

                            $filterRules = [
                                $key => 'trim|sanitize_email|lower_case'
                            ];

                            $validator->validation_rules($validatorRules);
                            $validator->filter_rules($filterRules);
                            $validatedData = $validator->run([
                                $key => $value
                            ]);

                            if($validatedData){
                                $item = $db->quote($validatedData[$key]);

                                $query .= <<<"query"
                                    if (select 1 from users where _email = {$item}) then
                                        set errors = concat(errors,if(errors = '[','',','),'{"email":"Email already exists"}');
                                    else
                                        update users set _email = {$item} where id = pointerId;

                                        if not row_count() then
                                            set errors = concat(errors,if(errors = '[','',','),'{"email":"An error occured while updating email"}');
                                        else
                                            set hasUpdate = 1;
                                        end if;
                                    end if;
query;
                            }
                        break;
                        case 'country':
                            $value = $this->getUtils()->getDataOfHash($value);
                            if(is_numeric($value)){
                                $item = $db->quote($value);

                                $query .= <<<"query"
                                    if not (select 1 from countries where id = {$item}) then
                                        set errors = concat(errors,if(errors = '[','',','),'{"country":"Invalid Country Selected"}');
                                    else
                                        update users set _country_id = {$item} where id = pointerId;

                                        if not row_count() then
                                            set errors = concat(errors,if(errors = '[','',','),'{"country":"An error occured while updating country"}');
                                        else
                                            set hasUpdate = 1;
                                        end if;
                                    end if;
query;
                            }
                        break;
                        case 'region':
                            $value = $this->getUtils()->getDataOfHash($value);
                            if(is_numeric($value)){
                                $item = $db->quote($value);

                                $query .= <<<"query"
                                    if not (select 1 from regions where id = {$item}) then
                                        set errors = concat(errors,if(errors = '[','',','),'{"region":"Invalid Region Selected"}');
                                    else
                                        update users set _region_id = {$item} where id = pointerId;

                                        if not row_count() then
                                            set errors = concat(errors,if(errors = '[','',','),'{"region":"An error occured while updating region"}');
                                        else
                                            set hasUpdate = 1;
                                        end if;
                                    end if;
query;
                            }
                        break;
                        case 'lga':
                            $value = $this->getUtils()->getDataOfHash($value);
                            if(is_numeric($value)){
                                $item = $db->quote($value);

                                $query .= <<<"query"
                                    if not (select 1 from lgas where id = {$item}) then
                                        set errors = concat(errors,if(errors = '[','',','),'{"lga":"Invalid Local Government Area Selected"}');
                                    else
                                        update users set _lga_id = {$item} where id = pointerId;

                                        if not row_count() then
                                            set errors = concat(errors,if(errors = '[','',','),'{"lga":"An error occured while updating lga"}');
                                        else
                                            set hasUpdate = 1;
                                        end if;
                                    end if;
query;
                            }
                        break;
                        case 'stateOfResidence':
                            $value = $this->getUtils()->getDataOfHash($value);
                            if(is_numeric($value)){
                                $item = $db->quote($value);

                                $result = $db->query("select _data as data from users where id = {$db->quote($id)} limit 1")->result();
                                if($result){
                                    $result = json_decode($result[0]['data'],true);
                                    if(!is_array($result)){
                                        $result = [];
                                    }

                                    $this->getUtils()::setDataInArray($result,'stateOfResidence',[
                                        'id' => $value
                                    ]);

                                    $db->prepare(<<<"query"
                                        begin not atomic
                                            `inner_process`: begin
                                                update users set _data = :data,_token = substr(unix_timestamp(),1,15) where id = {$db->quote($id)};
                                            end;
                                        end;
query
                                        )->bind([
                                            'data' => json_encode($result)
                                    ])->result(false);
                                }
                            }
                        break;
                        case 'isApproved':
                            $value = (int) $value;
                            if(in_array($value,[
                                1,
                                0
                            ],true)){
                                $item = $db->quote($value);

                                $query .= <<<"query"
                                    update users set _is_approved = {$item} where id = pointerId;

                                    if not row_count() then
                                        set errors = concat(errors,if(errors = '[','',','),'{"isApproved":"An error occured while updating approval status"}');
                                    else
                                        set hasUpdate = 1;
                                    end if;
query;
                            }
                        break;
                        case 'isBlocked':
                            $value = (int) $value;
                            if(in_array($value,[
                                1,
                                0
                            ],true)){
                                $item = $db->quote($value);

                                $query .= <<<"query"
                                    update users set _is_blocked = {$item} where id = pointerId;

                                    if not row_count() then
                                        set errors = concat(errors,if(errors = '[','',','),'{"isBlocked":"An error occured while updating blocked status"}');
                                    else
                                        set hasUpdate = 1;
                                    end if;
query;
                            }
                        break;
                        case 'isVerified':
                            $value = (int) $value;
                            if(in_array($value,[
                                1,
                                0
                            ],true)){
                                $item = $db->quote($value);

                                $query .= <<<"query"
                                    update users set _is_verified = {$item} where id = pointerId;

                                    if not row_count() then
                                        set errors = concat(errors,if(errors = '[','',','),'{"isVerified":"An error occured while updating verification status"}');
                                    else
                                        set hasUpdate = 1;
                                    end if;
query;
                            }
                        break;
                        case 'password':
                            if(!($value && hash_equals($value,($this->getUtils()::getDataFromArray($data,'confirmPassword') ?: '')))){
                                return [
                                    'status' => 'error',
                                    'response' => "Password do not match..."
                                ];
                            }

                            $value = $userManager->generateHashedPassword($value);
                            $item = $db->quote($value);

                            $query .= <<<"query"
                                update users set _password = {$item} where id = pointerId;

                                if not row_count() then
                                    set errors = concat(errors,if(errors = '[','',','),'{"password":"An error occured while updating password"}');
                                else
                                    set hasUpdate = 1;
                                end if;
query;
                        break;
                    }
                }
            }else{
                if($id){
                    $hasSetId = null;

                    switch($key){
                        case 'student':
                            $studentId = 0;
                            foreach($value as $studentKey => $studentValue){
                                if(is_null($hasSetId)){
                                    $studentId = $this->getUtils()->getDataOfHash($value['id'] ?? '');
                                    if(is_numeric($studentId)){
                                        $hasSetId = 1;
                                        $StudentId = $db->quote($studentId);
                                        $UserId = $db->quote($id);

                                        $query .= <<<"query"
                                            case {$db->quote($controllerType)}
                                                when 'all' then
                                                    if (select 1 from users as user join students as student on (student._user_id = user.id) where user.id = {$UserId} and student.id = {$StudentId}) then
                                                        set pointerId = {$StudentId},isValidId = 1;
                                                    else
                                                        set pointerId = 0,isValidId = 0;
                                                    end if;
                                                when 'school-all' then
                                                    if (select 1 from users as user join students as student on (user._type = 'student' and student._user_id = user.id) join secondary_schools as school on (student._secondary_school_id = school.id and school.id = {$db->quote($this->getSession()->get('adminData-->secondary-school-administrator-->stakeholderId'))}) where user.id = {$UserId} and student.id = {$StudentId}) then
                                                        set pointerId = {$StudentId},isValidId = 1;
                                                    else
                                                        set pointerId = 0,isValidId = 0;
                                                    end if;
                                                when 'basic' then
                                                    if (select 1 from users as user join students as student on (student._user_id = user.id) where user.id = {$UserId} and user.id = {$db->quote($this->getSession()->get('userData-->id'))} and student.id = {$StudentId}) then
                                                        set pointerId = {$StudentId},isValidId = 1;
                                                    else
                                                        set pointerId = 0,isValidId = 0;
                                                    end if;
                                            end case;

                                            if not isValidId then
                                                select '{"status":"error","response":"Invalid Student Request."}' as response;
                                                leave `inner_process`;
                                            end if;
query;
                                    }else{
                                        $hasSetId = 0;
                                    }
                                }

                                if(!is_array($studentValue)){
                                    $exists = array_search($studentKey,($this->getUtils()::getDataFromArray($checkList,'student') ?: []),true);

                                    if(is_numeric($exists)){
                                        if(isset(array_flip([
                                            'id',
                                            'image'
                                        ])[$studentKey]) || ($hasSetId === 0)){
                                            continue;
                                        }

                                        switch($studentKey){
                                            case 'class':
                                                $validatorRules = [
                                                    $studentKey => 'required|contains_list,jss3;sss1;sss2;sss3',
                                                ];

                                                $filterRules = [
                                                    $studentKey => 'trim|sanitize_string|lower_case'
                                                ];

                                                $validator->validation_rules($validatorRules);
                                                $validator->filter_rules($filterRules);
                                                $validatedData = $validator->run([
                                                    $studentKey => $studentValue
                                                ]);

                                                if($validatedData){
                                                    $item = $db->quote($validatedData[$studentKey]);

                                                    $query .= <<<"query"
                                                        update students set _level = {$item} where id = pointerId;

                                                        if not row_count() then
                                                            set errors = concat(errors,if(errors = '[','',','),'{"student.class":"An error occured while updating student\'s class"}');
                                                        else
                                                            set hasUpdate = 1;
                                                        end if;
query;
                                                }
                                            break;

                                            case 'dob':
                                                if(($date = strtotime($studentValue)) !== false){
                                                    $item = $db->quote(date('Y-m-d H:i:s',$date));

                                                    $query .= <<<"query"
                                                        update students set _dob = {$item} where id = pointerId;

                                                        if not row_count() then
                                                            set errors = concat(errors,if(errors = '[','',','),'{"student.dob":"An error occured while updating student\'s date of birth"}');
                                                        else
                                                            set hasUpdate = 1;
                                                        end if;
query;
                                                }
                                            break;
                                        }
                                    }
                                }else{
                                    if($studentId){
                                        $hasSetId = null;

                                        switch($studentKey){
                                            case 'school':
                                                $schoolId = 0;
                                                foreach($studentValue as $schoolKey => $schoolValue){
                                                    if(is_null($hasSetId)){
                                                        $schoolId = $this->getUtils()->getDataOfHash($studentValue['id'] ?? '');
                                                        if(is_numeric($schoolId)){
                                                            $hasSetId = 1;
                                                            $StudentId = $db->quote($studentId);
                                                            $UserId = $db->quote($id);
                                                            $SchoolId = $db->quote($schoolId);

                                                            $query .= <<<"query"
                                                                case {$db->quote($controllerType)}
                                                                    when 'all' then
                                                                        if (select 1 from users as user join students as student on (student._user_id = user.id) join secondary_schools as school on (student._secondary_school_id = school.id) where user.id = {$UserId} and student.id = {$StudentId} and school.id = {$SchoolId}) then
                                                                            set pointerId = {$SchoolId},isValidId = 1;
                                                                        else
                                                                            set pointerId = 0,isValidId = 0;
                                                                        end if;
                                                                    when 'school-all' then
                                                                        if (select 1 from users as user join students as student on (user._type = 'student' and student._user_id = user.id) join secondary_schools as school on (student._secondary_school_id = school.id and school.id = {$db->quote($this->getSession()->get('adminData-->secondary-school-administrator-->stakeholderId'))}) where user.id = {$UserId} and student.id = {$StudentId} and school.id = {$SchoolId}) then
                                                                            set pointerId = {$SchoolId},isValidId = 1;
                                                                        else
                                                                            set pointerId = 0,isValidId = 0;
                                                                        end if;
                                                                    when 'basic' then
                                                                        set pointerId = 0,isValidId = 0;
                                                                end case;

                                                                if not isValidId then
                                                                    select '{"status":"error","response":"Invalid Student School Request."}' as response;
                                                                    leave `inner_process`;
                                                                end if;
query;
                                                            if(is_numeric(array_search('image',($this->getUtils()::getDataFromArray($checkList,'student-->school') ?: []),true))){
                                                                $coverImage = $uploader->getFileDataFor("student-->school-->image-->tmp_name");
                                                                if($coverImage){
                                                                    $finfo = \finfo_open(FILEINFO_MIME_TYPE);
                                                                    $mime = \finfo_file($finfo,$coverImage);

                                                                    if(preg_match('#^image\/.*$#',$mime)){
                                                                        $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');
                                                                        if($mainCoverImage){
                                                                            $result = $db->query("select _data as data from secondary_schools where id = {$SchoolId} limit 1")->result();
                                                                            if($result){
                                                                                $previousImage = '';
                                                                                $result = json_decode($result[0]['data'],true);
                                                                                if(is_array($result)){
                                                                                    $previousImage = $this->getUtils()::getDataFromArray($result,'cover-image');
                                                                                }else{
                                                                                    $result = [];
                                                                                }

                                                                                $this->getUtils()::setDataInArray($result,'cover-image',$mainCoverImage);
                                                                                $result = $db->prepare(<<<"query"
                                                                                    begin not atomic
                                                                                        `inner_process`: begin
                                                                                            update secondary_schools set _data = :data where id = {$SchoolId};
                                                                                            if row_count() then
                                                                                                update users set _token = substr(unix_timestamp(),1,15) where id = {$db->quote($id)};
                                                                                            end if;
                                                                                        end;
                                                                                    end;
query
                                                                                    )->bind([
                                                                                    'data' => json_encode($result)
                                                                                ])->result(false);

                                                                                if($previousImage){
                                                                                    @unlink($previousImage);
                                                                                }
                                                                            }
                                                                        }else{
                                                                            unlink($coverImage);
                                                                        }
                                                                    }else{
                                                                        unlink($coverImage);
                                                                    }
                                                                }
                                                            }
                                                        }else{
                                                            $hasSetId = 0;
                                                        }
                                                    }

                                                    if(!is_array($schoolValue)){
                                                        $exists = array_search($schoolKey,($this->getUtils()::getDataFromArray($checkList,'student-->school') ?: []),true);

                                                        if(is_numeric($exists)){
                                                            if(isset(array_flip([
                                                                'id',
                                                                'image'
                                                            ])[$schoolKey]) || ($hasSetId === 0)){
                                                                continue;
                                                            }

                                                            switch($schoolKey){
                                                                case 'name':
                                                                    $validatorRules = [
                                                                        $schoolKey => 'required|min_len,3|max_len,255'
                                                                    ];

                                                                    $filterRules = [
                                                                        $schoolKey => 'trim|sanitize_string|lower_case'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $schoolKey => $schoolValue
                                                                    ]);

                                                                    if($validatedData){
                                                                        $item = $db->quote($validatedData[$schoolKey]);

                                                                        $query .= <<<"query"
                                                                            update secondary_schools set _name = {$item} where id = pointerId;

                                                                            if not row_count() then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.name":"An error occured while updating student\'s school name"}');
                                                                            else
                                                                                set hasUpdate = 1;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'uniqueName':
                                                                    $validatorRules = [
                                                                        $schoolKey => 'min_len,3|max_len,255'
                                                                    ];

                                                                    $filterRules = [
                                                                        $schoolKey => 'trim|sanitize_string'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $schoolKey => $schoolValue
                                                                    ]);

                                                                    if($validatedData){
                                                                        $item = $db->quote($validatedData[$schoolValue]);

                                                                        $query .= <<<"query"
                                                                            if (select 1 from secondary_schools where _unique_name = {$item}) then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.uniqueName":"Student School unique name already exists"}');
                                                                            else
                                                                                update secondary_schools set _unique_name = {$item} where id = pointerId;

                                                                                if not row_count() then
                                                                                    set errors = concat(errors,if(errors = '[','',','),'{"student.school.uniqueName":"An error occured while updating student school unique name"}');
                                                                                else
                                                                                    set hasUpdate = 1;
                                                                                end if;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'country':
                                                                    $schoolValue = $this->getUtils()->getDataOfHash($schoolValue);
                                                                    if(is_numeric($schoolValue)){
                                                                        $item = $db->quote($schoolValue);

                                                                        $query .= <<<"query"
                                                                            if not (select 1 from countries where id = {$item}) then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.country":"Invalid Country Selected for student school"}');
                                                                            else
                                                                                update secondary_schools set _country_id = {$item} where id = pointerId;

                                                                                if not row_count() then
                                                                                    set errors = concat(errors,if(errors = '[','',','),'{"student.school.country":"An error occured while updating student school country"}');
                                                                                else
                                                                                    set hasUpdate = 1;
                                                                                end if;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'region':
                                                                    $schoolValue = $this->getUtils()->getDataOfHash($schoolValue);
                                                                    if(is_numeric($schoolValue)){
                                                                        $item = $db->quote($schoolValue);

                                                                        $query .= <<<"query"
                                                                            if not (select 1 from regions where id = {$item}) then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.region":"Invalid Region Selected for student school"}');
                                                                            else
                                                                                update secondary_schools set _region_id = {$item} where id = pointerId;

                                                                                if not row_count() then
                                                                                    set errors = concat(errors,if(errors = '[','',','),'{"student.school.region":"An error occured while updating student school region"}');
                                                                                else
                                                                                    set hasUpdate = 1;
                                                                                end if;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'lga':
                                                                    $schoolValue = $this->getUtils()->getDataOfHash($schoolValue);
                                                                    if(is_numeric($schoolValue)){
                                                                        $item = $db->quote($schoolValue);

                                                                        $query .= <<<"query"
                                                                            if not (select 1 from lgas where id = {$item}) then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.lga":"Invalid Local Government Area Selected For Student School"}');
                                                                            else
                                                                                update secondary_schools set _lga_id = {$item} where id = pointerId;

                                                                                if not row_count() then
                                                                                    set errors = concat(errors,if(errors = '[','',','),'{"student.school.lga":"An error occured while updating student school lga"}');
                                                                                else
                                                                                    set hasUpdate = 1;
                                                                                end if;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'isApproved':
                                                                    $schoolValue = (int) $schoolValue;
                                                                    if(in_array($schoolValue,[
                                                                        1,
                                                                        0
                                                                    ],true)){
                                                                        $item = $db->quote($schoolValue);

                                                                        $query .= <<<"query"
                                                                            update secondary_schools set _is_approved = {$item} where id = pointerId;

                                                                            if not row_count() then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.isApproved":"An error occured while updating approval status"}');
                                                                            else
                                                                                set hasUpdate = 1;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'isBlocked':
                                                                    $schoolValue = (int) $schoolValue;
                                                                    if(in_array($schoolValue,[
                                                                        1,
                                                                        0
                                                                    ],true)){
                                                                        $item = $db->quote($schoolValue);

                                                                        $query .= <<<"query"
                                                                            update secondary_schools set _is_blocked = {$item} where id = pointerId;

                                                                            if not row_count() then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.isBlocked":"An error occured while updating blocked status"}');
                                                                            else
                                                                                set hasUpdate = 1;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'address':
                                                                    $validatorRules = [
                                                                        $schoolKey => 'required|min_len,5|max_len,255',
                                                                    ];

                                                                    $filterRules = [
                                                                        $schoolKey => 'trim|sanitize_string|lower_case'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $schoolKey => $schoolValue
                                                                    ]);

                                                                    if($validatedData){
                                                                        $item = $db->quote($validatedData[$schoolKey]);

                                                                        $query .= <<<"query"
                                                                            update secondary_schools set _address = {$item} where id = pointerId;

                                                                            if not row_count() then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.address":"An error occured while updating address"}');
                                                                            else
                                                                                set hasUpdate = 1;
                                                                            end if;
query;
                                                                    }
                                                                break;

                                                                case 'description':
                                                                    $validatorRules = [
                                                                        $schoolKey => 'required|min_len,5|max_len,2000',
                                                                    ];

                                                                    $filterRules = [
                                                                        $schoolKey => 'trim|sanitize_string|lower_case'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $schoolKey => $schoolValue
                                                                    ]);

                                                                    if($validatedData){
                                                                        $item = $db->quote($validatedData[$schoolKey]);

                                                                        $query .= <<<"query"
                                                                            update secondary_schools set _description = {$item} where id = pointerId;

                                                                            if not row_count() then
                                                                                set errors = concat(errors,if(errors = '[','',','),'{"student.school.description":"An error occured while updating description"}');
                                                                            else
                                                                                set hasUpdate = 1;
                                                                            end if;
query;
                                                                    }
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }
                                            break;
                                        }
                                    }
                                }
                            }
                        break;

                        case 'admin':
                            foreach($value as $adminKey => $adminValue){
                                if(is_array($adminValue)){
                                    $adminId = 0;
                                    foreach($adminValue as $aKey => $aValue){
                                        if(is_null($hasSetId)){
                                            $adminId = $this->getUtils()->getDataOfHash($adminValue['id'] ?? '');
                                            if(is_numeric($adminId)){
                                                $hasSetId = 1;
                                                $AdminId = $db->quote($adminId);
                                                $UserId = $db->quote($id);

                                                $query .= <<<"query"
                                                    case {$db->quote($controllerType)}
                                                        when 'all' then
                                                            if (select 1 from users as user join administrators as admin on (user.id = admin._user_id) where user.id = {$UserId} and admin.id = {$AdminId}) then
                                                                set pointerId = {$AdminId},isValidId = 1;
                                                            else
                                                                set pointerId = 0,isValidId = 0;
                                                            end if;
                                                        else
                                                            set pointerId = 0,isValidId = 0;
                                                    end case;

                                                    if not isValidId then
                                                        select '{"status":"error","response":"Invalid administrator update request"}' as response;
                                                        leave `inner_process`;
                                                    end if;
query;
                                            }else{
                                                $hasSetId = 0;
                                            }
                                        }

                                        if(!is_array($aValue)){
                                            $exists = array_search($aKey,($this->getUtils()::getDataFromArray($checkList,'admin') ?: []),true);

                                            if(is_numeric($exists)){
                                                if(isset(array_flip([
                                                    'id',
                                                    'image'
                                                ])[$aKey]) || ($hasSetId === 0)){
                                                    continue;
                                                }

                                                switch($aKey){
                                                    case 'isApproved':
                                                        $aValue = (int) $aValue;
                                                        if(in_array($aValue,[
                                                            1,
                                                            0
                                                        ],true)){
                                                            $item = $db->quote($aValue);

                                                            $query .= <<<"query"
                                                                update administrators set _is_approved = {$item} where id = pointerId;

                                                                if not row_count() then
                                                                    set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.isApproved":"An error occured while updating approval status"}');
                                                                else
                                                                    set hasUpdate = 1;
                                                                end if;
query;
                                                        }
                                                    break;

                                                    case 'isBlocked':
                                                        $aValue = (int) $aValue;
                                                        if(in_array($aValue,[
                                                            1,
                                                            0
                                                        ],true)){
                                                            $item = $db->quote($aValue);

                                                            $query .= <<<"query"
                                                                update administrators set _is_blocked = {$item} where id = pointerId;

                                                                if not row_count() then
                                                                    set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.isBlocked":"An error occured while updating blocked status"}');
                                                                else
                                                                    set hasUpdate = 1;
                                                                end if;
query;
                                                        }
                                                    break;
                                                }
                                            }
                                        }else{
                                            if($adminId){
                                                $hasSetId = null;

                                                switch($aKey){
                                                    case 'stakeholder':
                                                        $stakeholderId = 0;
                                                        foreach($aValue as $stakeholderKey => $stakeholderValue){
                                                            $adminTable;

                                                            if(is_null($hasSetId)){
                                                                $stakeholderId = $this->getUtils()->getDataOfHash($aValue['id'] ?? '');
                                                                if(is_numeric($stakeholderId)){
                                                                    $hasSetId = 1;
                                                                    $StakeholderId = $db->quote($stakeholderId);
                                                                    $UserId = $db->quote($id);
                                                                    $AdminId = $db->quote($adminId);

                                                                    $result = $db->query("select _type as type from administrators where id = {$AdminId} limit 1")->result();

                                                                    if(!$result){
                                                                        continue;
                                                                    }

                                                                    $adminType = $result[0]['type'];

                                                                    $adminTable = (($adminType == 'internship-provider-administrator') ? 'internship_providers' : (($adminType == 'secondary-school-administrator') ? 'secondary_schools' : (($adminType == 'institution-administrator') ? 'institutions' : null)));

                                                                    if(!$adminTable){
                                                                        continue;
                                                                    }

                                                                    $query .= <<<"query"
                                                                        case {$db->quote($controllerType)}
                                                                            when 'all' then
                                                                                if (select 1 from users as user join administrators as admin on (admin._user_id = user.id) join {$adminTable} as stakeholder on (admin._stakeholder_id = stakeholder.id) where user.id = {$UserId} and admin.id = {$AdminId} and stakeholder.id = {$StakeholderId}) then
                                                                                    set pointerId = {$StakeholderId},isValidId = 1;
                                                                                else
                                                                                    set pointerId = 0,isValidId = 0;
                                                                                end if;
                                                                            else
                                                                                set pointerId = 0,isValidId = 0;
                                                                        end case;

                                                                        if not isValidId then
                                                                            select '{"status":"error","response":"Invalid Stakeholder Request."}' as response;
                                                                            leave `inner_process`;
                                                                        end if;
query;
                                                                    if(is_numeric(array_search('image',($this->getUtils()::getDataFromArray($checkList,'admin-->stakeholder') ?: []),true))){
                                                                        $coverImage = $uploader->getFileDataFor("admin-->{$adminKey}-->stakeholder-->image-->tmp_name");
                                                                        if($coverImage){
                                                                            $finfo = \finfo_open(FILEINFO_MIME_TYPE);
                                                                            $mime = \finfo_file($finfo,$coverImage);

                                                                            if(preg_match('#^image\/.*$#',$mime)){
                                                                                $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');
                                                                                if($mainCoverImage){
                                                                                    $result = $db->query("select _data as data from {$adminTable} where id = {$StakeholderId} limit 1")->result();
                                                                                    if($result){
                                                                                        $previousImage = '';
                                                                                        $result = json_decode($result[0]['data'],true);
                                                                                        if(is_array($result)){
                                                                                            $previousImage = $this->getUtils()::getDataFromArray($result,'cover-image');
                                                                                        }else{
                                                                                            $result = [];
                                                                                        }

                                                                                        $this->getUtils()::setDataInArray($result,'cover-image',$mainCoverImage);
                                                                                        $result = $db->prepare(<<<"query"
                                                                                            begin not atomic
                                                                                                `inner_process`: begin
                                                                                                    update {$adminTable} set _data = :data where id = {$StakeholderId};
                                                                                                    if row_count() then
                                                                                                        update users set _token = substr(unix_timestamp(),1,15) where id = {$db->quote($id)};
                                                                                                    end if;
                                                                                                end;
                                                                                            end;
query
                                                                                            )->bind([
                                                                                            'data' => json_encode($result)
                                                                                        ])->result(false);

                                                                                        if($previousImage){
                                                                                            @unlink($previousImage);
                                                                                        }
                                                                                    }
                                                                                }else{
                                                                                    unlink($coverImage);
                                                                                }
                                                                            }else{
                                                                                unlink($coverImage);
                                                                            }
                                                                        }
                                                                    }
                                                                }else{
                                                                    $hasSetId = 0;
                                                                }
                                                            }

                                                            if(!is_array($stakeholderValue)){
                                                                $exists = array_search($stakeholderKey,($this->getUtils()::getDataFromArray($checkList,'admin-->stakeholder') ?: []),true);

                                                                if(is_numeric($exists)){
                                                                    if(isset(array_flip([
                                                                        'id',
                                                                        'image'
                                                                    ])[$stakeholderKey]) || ($hasSetId === 0)){
                                                                        continue;
                                                                    }

                                                                    switch($stakeholderKey){
                                                                        case 'name':
                                                                            $validatorRules = [
                                                                                $stakeholderKey => 'required|min_len,3|max_len,255'
                                                                            ];

                                                                            $filterRules = [
                                                                                $stakeholderKey => 'trim|sanitize_string|lower_case'
                                                                            ];

                                                                            $validator->validation_rules($validatorRules);
                                                                            $validator->filter_rules($filterRules);
                                                                            $validatedData = $validator->run([
                                                                                $stakeholderkey => $stakeholderValue
                                                                            ]);

                                                                            if($validatedData){
                                                                                $item = $db->quote($validatedData[$stakeholderKey]);

                                                                                $query .= <<<"query"
                                                                                    update {$adminTable} set _name = {$item} where id = pointerId;

                                                                                    if not row_count() then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.name":"An error occured while updating name"}');
                                                                                    else
                                                                                        set hasUpdate = 1;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'uniqueName':
                                                                            $validatorRules = [
                                                                                $stakeholderKey => 'min_len,3|max_len,255'
                                                                            ];

                                                                            $filterRules = [
                                                                                $stakeholderKey => 'trim|sanitize_string'
                                                                            ];

                                                                            $validator->validation_rules($validatorRules);
                                                                            $validator->filter_rules($filterRules);
                                                                            $validatedData = $validator->run([
                                                                                $stakeholderKey => $stakeholderValue
                                                                            ]);

                                                                            if($validatedData){
                                                                                $item = $db->quote($validatedData[$stakeholderValue]);

                                                                                $query .= <<<"query"
                                                                                    if (select 1 from {$adminTable} where _unique_name = {$item}) then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.uniqueName":"unique name already exists"}');
                                                                                    else
                                                                                        update {$adminTable} set _unique_name = {$item} where id = pointerId;

                                                                                        if not row_count() then
                                                                                            set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.uniqueName":"An error occured while updating unique name"}');
                                                                                        else
                                                                                            set hasUpdate = 1;
                                                                                        end if;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'country':
                                                                            $stakeholderValue = $this->getUtils()->getDataOfHash($stakeholderValue);
                                                                            if(is_numeric($stakeholderValue)){
                                                                                $item = $db->quote($stakeholderValue);

                                                                                $query .= <<<"query"
                                                                                    if not (select 1 from countries where id = {$item}) then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.country":"Invalid Country Selected"}');
                                                                                    else
                                                                                        update {$adminTable} set _country_id = {$item} where id = pointerId;

                                                                                        if not row_count() then
                                                                                            set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.country":"An error occured while updating country"}');
                                                                                        else
                                                                                            set hasUpdate = 1;
                                                                                        end if;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'region':
                                                                            $stakeholderValue = $this->getUtils()->getDataOfHash($stakeholderValue);
                                                                            if(is_numeric($stakeholderValue)){
                                                                                $item = $db->quote($stakeholderValue);

                                                                                $query .= <<<"query"
                                                                                    if not (select 1 from regions where id = {$item}) then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.region":"Invalid Region Selected"}');
                                                                                    else
                                                                                        update {$adminTable} set _region_id = {$item} where id = pointerId;

                                                                                        if not row_count() then
                                                                                            set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.region":"An error occured while updating region"}');
                                                                                        else
                                                                                            set hasUpdate = 1;
                                                                                        end if;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'lga':
                                                                            $stakeholderValue = $this->getUtils()->getDataOfHash($stakeholderValue);
                                                                            if(is_numeric($stakeholderValue)){
                                                                                $item = $db->quote($stakeholderValue);

                                                                                $query .= <<<"query"
                                                                                    if not (select 1 from lgas where id = {$item}) then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.lga":"Invalid Local Government Area Selected"}');
                                                                                    else
                                                                                        update {$adminTable} set _lga_id = {$item} where id = pointerId;

                                                                                        if not row_count() then
                                                                                            set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.lga":"An error occured while updating lga"}');
                                                                                        else
                                                                                            set hasUpdate = 1;
                                                                                        end if;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'isApproved':
                                                                            $stakeholderValue = (int) $stakeholderValue;
                                                                            if(in_array($stakeholderValue,[
                                                                                1,
                                                                                0
                                                                            ],true)){
                                                                                $item = $db->quote($stakeholderValue);

                                                                                $query .= <<<"query"
                                                                                    update {$adminTable} set _is_approved = {$item} where id = pointerId;

                                                                                    if not row_count() then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.isApproved":"An error occured while updating approval status"}');
                                                                                    else
                                                                                        set hasUpdate = 1;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'isBlocked':
                                                                            $stakeholderValue = (int) $stakeholderValue;
                                                                            if(in_array($stakeholderValue,[
                                                                                1,
                                                                                0
                                                                            ],true)){
                                                                                $item = $db->quote($stakeholderValue);

                                                                                $query .= <<<"query"
                                                                                    update {$adminTable} set _is_blocked = {$item} where id = pointerId;

                                                                                    if not row_count() then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.isBlocked":"An error occured while updating blocked status"}');
                                                                                    else
                                                                                        set hasUpdate = 1;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'address':
                                                                            $validatorRules = [
                                                                                $stakeholderKey => 'required|min_len,5|max_len,255',
                                                                            ];

                                                                            $filterRules = [
                                                                                $stakeholderKey => 'trim|sanitize_string|lower_case'
                                                                            ];

                                                                            $validator->validation_rules($validatorRules);
                                                                            $validator->filter_rules($filterRules);
                                                                            $validatedData = $validator->run([
                                                                                $stakeholderKey => $stakeholderValue
                                                                            ]);

                                                                            if($validatedData){
                                                                                $item = $db->quote($validatedData[$stakeholderKey]);

                                                                                $query .= <<<"query"
                                                                                    update {$adminTable} set _address = {$item} where id = pointerId;

                                                                                    if not row_count() then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.address":"An error occured while updating address"}');
                                                                                    else
                                                                                        set hasUpdate = 1;
                                                                                    end if;
query;
                                                                            }
                                                                        break;

                                                                        case 'description':
                                                                            $validatorRules = [
                                                                                $stakeholderKey => 'required|min_len,5|max_len,2000',
                                                                            ];

                                                                            $filterRules = [
                                                                                $stakeholderKey => 'trim|sanitize_string|lower_case'
                                                                            ];

                                                                            $validator->validation_rules($validatorRules);
                                                                            $validator->filter_rules($filterRules);
                                                                            $validatedData = $validator->run([
                                                                                $stakeholderKey => $stakeholderValue
                                                                            ]);

                                                                            if($validatedData){
                                                                                $item = $db->quote($validatedData[$stakeholderKey]);

                                                                                $query .= <<<"query"
                                                                                    update {$adminTable} set _description = {$item} where id = pointerId;

                                                                                    if not row_count() then
                                                                                        set errors = concat(errors,if(errors = '[','',','),'{"admin.{$adminKey}.stakeholder.description":"An error occured while updating description"}');
                                                                                    else
                                                                                        set hasUpdate = 1;
                                                                                    end if;
query;
                                                                            }
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        break;
                    }
                }
            }
        }

        $query .= <<<"query"
                set errors = concat(errors,']');
                if hasUpdate then
                    update users set _token = substr(unix_timestamp(),1,15) where id = {$db->quote($id)};
                end if;

                select concat('{"status":"ok","response":"Data updated succesfully","errors":',errors,'}') as response;
                leave `inner_process`;
            end;
        end;
query;

        $result = $db->query($query)->result(true);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(is_array($result)){
                if($result['status'] == 'ok' && is_array($result['errors']) && count($result['errors'])){
                    return [
                        'status' => 'error',
                        'errors' => $result['errors'],
                        'response' => 'An error occured'
                    ];
                }
                return $result;
            }
            return $this->getUtils()::getResponseFor('malformed-db-response');
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function addUserPortfolio($data){
        $db = $this->getDatabase();
        $validator = $this->getUtils()->init('General-Validator');
        $uploader = $this->getUtils()->init('General-Uploader');
        $usersManager = $this->getUtils()->init('Users-Manager');
        $uploader->beginWatch();

        $data = json_decode($data,true);

        if(!is_array($data)){
            return $this->getUtils()::getResponseFor('invalid-request');
        }

        $accountType = strtolower($this->getUtils()::getDataFromArray($data,'type'));
        $isCouncellor = $usersManager->hasPermissionAs('secondary-school-administrator');
        $isGeneralAdmin = $usersManager->hasPermissionAs('general-administrator');

        $query = <<<"query"
            begin not atomic
                `inner_process`: begin
                    declare userId int default 0;
                    declare adminId int default 0;
                    declare stakeholderId int default 0;
                    declare studentId int default 0;
                    declare schoolId int default 0;

                    start transaction;

                    insert into users(_name,_password,_country_id,_region_id,_lga_id,_type,_token,_has_complete_registration,_data) values ('','',0,0,0,{$db->quote($accountType)},substr(unix_timestamp(),1,15),1,'');
                    set userId = last_insert_id();

                    if not userId then
                        select '{"status":"error","response":"An error occurred while initiating user commit"}' as response;
                        rollback;
                        leave `inner_process`;
                    end if;
query;

        switch(true){
            case (is_numeric(strpos($accountType,'administrator'))):
                if(!$isGeneralAdmin){
                    return $this->getUtils()::getResponseFor('invalid-request');
                }
            break;

            case ($accountType === 'student'):
                if(!($isGeneralAdmin || $isCouncellor)){
                    return $this->getUtils()::getResponseFor('invalid-request');
                }
            break;
        }

        $councellorCheckList = [
            'name',
            'optional' => [
                'username',
                'email',
                'phoneNumber',
            ],
            'country',
            'region',
            'lga',
            'stateOfResidence',
            'image',
            'isApproved',
            'isBlocked',
            'isVerified',
            'password',
            'confirmPassword',
            'student' => [
                'dob',
                'class',
                'school' => [
                    'created' => [
                        'name',
                        'uniqueName',
                        'address',
                        'country',
                        'lga',
                        'region',
                        'description',
                        'image'
                    ],
                    'selected' => [
                        'id'
                    ]
                ]
            ]
        ];

        $generalAdminCheckList = [
            'student' => [
                'school' => [
                    'created' => [
                        'isApproved',
                        'isBlocked'
                    ]
                ]
            ],
            'admin' => [
                'isApproved',
                'isBlocked',
                'stakeholder' => [
                    'selected' => [
                        'id'
                    ],
                    'created' => [
                        'name',
                        'uniqueName',
                        'address',
                        'country',
                        'lga',
                        'region',
                        'description',
                        'isApproved',
                        'isBlocked',
                        'image'
                    ]
                ]
            ]
        ];

        $adminPointers = [
            'institution-administrator' => 'institutions',
            'secondary-school-administrator' => 'secondary_schools',
            'internship-provider-administrator' => 'internship_providers'
        ];

        $imageLocations = [];
        $checkList = [];

        switch($accountType){
            case 'student':
                if($isCouncellor){
                    $checkList = $councellorCheckList;
                }else{
                    $checkList = array_merge_recursive($councellorCheckList,$generalAdminCheckList);
                }

                $this->getUtils()::removeDataFromArray($checkList,'admin');
            break;
            default:
                $checkList = array_merge_recursive($councellorCheckList,$generalAdminCheckList);
                $this->getUtils()::removeDataFromArray($checkList,'student');
            break;
        }

        if(is_numeric(array_search('image',$checkList,true))){
            $coverImage = $uploader->getFileDataFor("image-->tmp_name");
            if($coverImage){
                $finfo = \finfo_open(FILEINFO_MIME_TYPE);
                $mime = \finfo_file($finfo,$coverImage);

                if(preg_match('#^image\/.*$#',$mime)){
                    $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');
                    $value = $db->quote('"cover-image":"'.substr($db->quote($mainCoverImage),1,-1).'"');

                    if($mainCoverImage){
                        $imageLocations[] = $mainCoverImage;
                        $query .= <<<"query"
                            update users set _data = concat(_data,',',{$value}) where id = userId;

                            if not row_count() then
                                select '{"status":"error","response":"An error occured while setting user image","pointer":"image","isPicture":true}' as response;
                                rollback;
                                leave `inner_process`;
                            end if;
query;
                    }else{
                        unlink($coverImage);
                    }
                }else{
                    unlink($coverImage);
                }
            }
        }

        foreach($checkList as $checkKey => &$checkItem){
            if(!is_array($checkItem)){
                if(isset(array_flip([
                    'image',
                    'confirmPassword'
                ])[$checkItem])){
                    continue;
                }

                switch($checkItem){
                    case 'name':
                        $key = $checkItem;
                        $value = $this->getUtils()::getDataFromArray($data,$checkItem);

                        $validatorRules = [
                            $key => 'required|min_len,5|max_len,150',
                        ];

                        $filterRules = [
                            $key => 'trim|sanitize_string|lower_case'
                        ];

                        $validator->validation_rules($validatorRules);
                        $validator->filter_rules($filterRules);
                        $validatedData = $validator->run([
                            $key => $value
                        ]);

                        if($validatedData === false){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops a valid name is required'
                            ];
                        }

                        $item = $db->quote($validatedData[$key]);
                        $query .= <<<"query"
                            update users set _name = {$item} where id = userId;

                            if not row_count() then
                                select '{"status":"error","response":"An error occured while inserting name","pointer":"{$key}"}' as response;
                                rollback;
                                leave `inner_process`;
                            end if;
query;
                    break;
                    case 'country':
                        $key = $checkItem;
                        $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,$key));
                        if(!($value && is_numeric($value))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops a valid country is required'
                            ];
                        }

                        $item = $db->quote($value);
                        $query .= <<<"query"
                            if not (select 1 from countries where id = {$item}) then
                                select '{"status":"error","response":"Invalid country selected","pointer":"{$key}"}' as response;
                                rollback;
                                leave `inner_process`;
                            else
                                update users set _country_id = {$item} where id = userId;

                                if not row_count() then
                                    select '{"status":"error","response":"An error occured while inserting country","pointer":"{$key}"}' as response;
                                    rollback;
                                    leave `inner_process`;
                                end if;
                            end if;
query;
                    break;
                    case 'region':
                        $key = $checkItem;
                        $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,$key));
                        if(!($value && is_numeric($value))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops a valid region is required'
                            ];
                        }

                        $item = $db->quote($value);
                        $query .= <<<"query"
                            if not (select 1 from regions where id = {$item}) then
                                select '{"status":"error","response":"Invalid region selected","pointer":"{$key}"}' as response;
                                rollback;
                                leave `inner_process`;
                            else
                                update users set _region_id = {$item} where id = userId;

                                if not row_count() then
                                    select '{"status":"error","response":"An error occured while inserting region","pointer":"{$key}"}' as response;
                                    rollback;
                                    leave `inner_process`;
                                end if;
                            end if;
query;
                    break;
                    case 'lga':
                        $key = $checkItem;
                        $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,$key));
                        if(!($value && is_numeric($value))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops a valid lga is required'
                            ];
                        }

                        $item = $db->quote($value);
                        $query .= <<<"query"
                            if not (select 1 from lgas where id = {$item}) then
                                select '{"status":"error","response":"Invalid lga selected","pointer":"{$key}"}' as response;
                                rollback;
                                leave `inner_process`;
                            else
                                update users set _lga_id = {$item} where id = userId;

                                if not row_count() then
                                    select '{"status":"error","response":"An error occured while inserting lga","pointer":"{$key}"}' as response;
                                    rollback;
                                    leave `inner_process`;
                                end if;
                            end if;
query;
                    break;
                    case 'stateOfResidence':
                        $key = $checkItem;
                        $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,$key));
                        if(!($value && is_numeric($value))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops a valid region is required'
                            ];
                        }

                        $item = $value;
                        $value = $db->quote('"stateOfResidence":'.json_encode([
                            'id' => $value
                        ]));

                        $query .= <<<"query"
                            if not (select 1 from regions where id = {$item}) then
                                select '{"status":"error","response":"Invalid region selected","pointer":"{$key}"}' as response;
                                rollback;
                                leave `inner_process`;
                            else
                                update users set _data = concat(_data,',',{$value}) where id = userId;

                                if not row_count() then
                                    select '{"status":"error","response":"An error occured while inserting state of residence","pointer":"{$key}"}' as response;
                                    rollback;
                                    leave `inner_process`;
                                end if;
                            end if;
query;
                    break;
                    case 'isApproved':
                        $key = $checkItem;
                        $value = $this->getUtils()::getDataFromArray($data,$key);

                        if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops please select user approval status'
                            ];
                        }

                        $item = $db->quote((int) $value);
                        $query .= <<<"query"
                            update users set _is_approved = {$item} where id = userId;
query;
                    break;
                    case 'isBlocked':
                        $key = $checkItem;
                        $value = $this->getUtils()::getDataFromArray($data,$key);

                        if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops please select user blocked status'
                            ];
                        }

                        $item = $db->quote((int) $value);
                        $query .= <<<"query"
                            update users set _is_blocked = {$item} where id = userId;
query;
                    break;
                    case 'isVerified':
                        $key = $checkItem;
                        $value = $this->getUtils()::getDataFromArray($data,$key);

                        if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => 'Ooops please select user verification status'
                            ];
                        }

                        $item = $db->quote((int) $value);
                        $query .= <<<"query"
                            update users set _is_verified = {$item} where id = userId;
query;
                    break;
                    case 'password':
                        $key = $checkItem;
                        $value = $this->getUtils()::getDataFromArray($data,$key);
                        if(!($value && hash_equals($value,($this->getUtils()::getDataFromArray($data,'confirmPassword') ?: '')))){
                            return [
                                'status' => 'error',
                                'pointer' => $key,
                                'response' => "Password do not match..."
                            ];
                        }

                        $userManager = $this->getUtils()->init('Users-Manager');
                        $value = $userManager->generateHashedPassword($value);
                        $item = $db->quote($value);

                        $query .= <<<"query"
                            update users set _password = {$item} where id = userId;

                            if not row_count() then
                                select '{"status":"error","response":"An error occured while setting user password","pointer":"{$key}"}' as response;
                                rollback;
                                leave `inner_process`;
                            end if;
query;
                    break;
                }
            }else{
                switch($checkKey){
                    case 'optional':
                        $checkBox = [1,1,1];

                        foreach($checkItem as &$optionalItem){
                            switch($optionalItem){
                                case 'username':
                                    $key = $optionalItem;
                                    $value = $this->getUtils()::getDataFromArray($data,$key);

                                    $validatorRules = [
                                        $key => 'min_len,3|max_len,255'
                                    ];

                                    $filterRules = [
                                        $key => 'trim|sanitize_string'
                                    ];

                                    $validator->validation_rules($validatorRules);
                                    $validator->filter_rules($filterRules);
                                    $validatedData = $validator->run([
                                        $key => $value
                                    ]);

                                    if($validatedData === false){
                                        $checkBox[0] = 0;
                                        continue 2;
                                    }

                                    $item = $db->quote($validatedData[$key]);
                                    $query .= <<<"query"
                                        if (select 1 from users where _unique_name = {$item}) then
                                            select '{"status":"error","response":"Username already exists","pointer":"{$key}"}' as response;
                                            rollback;
                                            leave `inner_process`;
                                        else
                                            update users set _unique_name = {$item} where id = userId;

                                            if not row_count() then
                                                select '{"status":"error","response":"An error occured while setting user\'s username","pointer":"{$key}"}' as response;
                                                rollback;
                                                leave `inner_process`;
                                            end if;
                                        end if;
query;
                                break;
                                case 'phoneNumber':
                                    $key = $optionalItem;
                                    $value = $this->getUtils()::getDataFromArray($data,$key);

                                    $validatorRules = [
                                        $key => 'required|numeric|min_len,9|max_len,15'
                                    ];

                                    $filterRules = [
                                        $key => 'trim|sanitize_numbers'
                                    ];

                                    $validator->validation_rules($validatorRules);
                                    $validator->filter_rules($filterRules);
                                    $validatedData = $validator->run([
                                        $key => $value
                                    ]);

                                    if($validatedData === false){
                                        $checkBox[1] = 0;
                                        continue 2;
                                    }

                                    $item = $db->quote($validatedData[$key]);
                                    $query .= <<<"query"
                                    if (select 1 from users where _phone_number = {$item}) then
                                        select '{"status":"error","response":"Phone number already exists","pointer":"{$key}"}' as response;
                                        rollback;
                                        leave `inner_process`;
                                    else
                                        update users set _phone_number = {$item} where id = userId;

                                        if not row_count() then
                                            select '{"status":"error","response":"An error occured while setting user\'s phone number","pointer":"{$key}"}' as response;
                                            rollback;
                                            leave `inner_process`;
                                        end if;
                                    end if;
query;
                                break;
                                case 'email':
                                    $key = $optionalItem;
                                    $value = $this->getUtils()::getDataFromArray($data,$key);

                                    $validatorRules = [
                                        $key => 'min_len,3|max_len,255|valid_email'
                                    ];

                                    $filterRules = [
                                        $key => 'trim|sanitize_email|lower_case'
                                    ];

                                    $validator->validation_rules($validatorRules);
                                    $validator->filter_rules($filterRules);
                                    $validatedData = $validator->run([
                                        $key => $value
                                    ]);

                                    if($validatedData === false){
                                        $checkBox[2] = 0;
                                        continue 2;
                                    }

                                    $item = $db->quote($validatedData[$key]);
                                    $query .= <<<"query"
                                    if (select 1 from users where _email = {$item}) then
                                        select '{"status":"error","response":"Email already exists","pointer":"{$key}"}' as response;
                                        rollback;
                                        leave `inner_process`;
                                    else
                                        update users set _email = {$item} where id = userId;

                                        if not row_count() then
                                            select '{"status":"error","response":"An error occured while setting user\'s email","pointer":"{$key}"}' as response;
                                            rollback;
                                            leave `inner_process`;
                                        end if;
                                    end if;
query;
                                break;
                            }
                        }

                        if(!isset(array_flip($checkBox)[1])){
                            return [
                                'status' => 'error',
                                'pointer' => 'optional',
                                'response' => "A value is required for one of the following (username, email , phone number)"
                            ];
                        }
                    break;
                    case 'student':
                        $prefix = $checkKey;

                        $query .= <<<"query"
                            insert into students (_user_id,_secondary_school_id,_level) values (userId,0,'');
                            set studentId = last_insert_id();

                            if not studentId then
                                select '{"status":"error","response":"An error occurred while initiating student commit"}' as response;
                                rollback;
                                leave `inner_process`;
                            end if;
query;

                        foreach($checkItem as $studentKey => $studentItem){
                            if(!is_array($studentItem)){
                                switch($studentItem){
                                    case 'dob':
                                        $key = $studentItem;
                                        $locator = "{$prefix}.{$key}";
                                        $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                        if(($date = strtotime($value)) === false){
                                            return [
                                                'status' => 'error',
                                                'pointer' => $locator,
                                                'response' => "Please insert a valid date. kindly follow the example given"
                                            ];
                                        }

                                        $item = $db->quote(date('Y-m-d H:i:s',$date));

                                        $query .= <<<"query"
                                            update students set _dob = {$item} where id = studentId;

                                            if not row_count() then
                                                select '{"status":"error","response":"An error occured while setting student's date of birth","pointer":"{$locator}"}' as response;
                                                rollback;
                                                leave `inner_process`;
                                            end if;
query;
                                    break;
                                    case 'class':
                                        $key = $studentItem;
                                        $locator = "{$prefix}.{$key}";
                                        $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                        $validatorRules = [
                                            $key => 'required|contains_list,jss3;sss1;sss2;sss3',
                                        ];

                                        $filterRules = [
                                            $key => 'trim|sanitize_string|lower_case'
                                        ];

                                        $validator->validation_rules($validatorRules);
                                        $validator->filter_rules($filterRules);
                                        $validatedData = $validator->run([
                                            $key => $value
                                        ]);

                                        if($validatedData === false){
                                            return [
                                                'status' => 'error',
                                                'pointer' => $locator,
                                                'response' => "Please select a valid student class"
                                            ];
                                        }

                                        $item = $db->quote($validatedData[$key]);
                                        $query .= <<<"query"
                                            update students set _level = {$item} where id = studentId;

                                            if not row_count() then
                                                select '{"status":"error","response":"An error occured while setting student's class","pointer":"{$locator}"}' as response;
                                                rollback;
                                                leave `inner_process`;
                                            end if;
query;
                                    break;
                                }
                            }else{
                                switch($studentKey){
                                    case 'school':
                                        $prefix = "{$prefix}.{$studentKey}";
                                        $locator = "{$prefix}.mode";
                                        $mode = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                        switch($mode){
                                            case 'selected':
                                                $locator = "{$prefix}.id";
                                                $schoolId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                if(!($schoolId && is_numeric($schoolId))){
                                                    return [
                                                        'status' => 'error',
                                                        'pointer' => $locator,
                                                        'response' => "Please select a valid school"
                                                    ];
                                                }

                                                $item = $db->quote($schoolId);

                                                $query .= <<<"query"
                                                    if not (select 1 from secondary_schools where id = {$item} limit 1) then
                                                        select '{"status":"error","response":"Invalid secondary school selected","pointer":"{$locator}"}' as response;
                                                        rollback;
                                                        leave `inner_process`;
                                                    end if;

                                                    set schoolId = {$item};
                                                    update students set _secondary_school_id = schoolId where id = studentId;

                                                    if not row_count() then
                                                        select '{"status":"error","response":"An error occured while linking student to school","pointer":"{$locator}"}' as response;
                                                        rollback;
                                                        leave `inner_process`;
                                                    end if;
query;
                                            break;
                                            case 'created':
                                                if(!(isset($studentItem[$mode]) && is_array($studentItem[$mode]))){
                                                    return [
                                                        'status' => 'error',
                                                        'pointer' => 'general',
                                                        'response' => "Unknown check list for created school"
                                                    ];
                                                }

                                                $query .= <<<"query"
                                                    insert into secondary_schools (_unique_name,_name,_country_id,_region_id,_lga_id,_address,_data) values ('','',0,0,0,'','');
                                                    set schoolId = last_insert_id();

                                                    if not schoolId then
                                                        select '{"status":"error","response":"An error occurred while initiating student commit"}' as response;
                                                        rollback;
                                                        leave `inner_process`;
                                                    else
                                                        update students set _secondary_school_id = schoolId where id = studentId;

                                                        if not row_count() then
                                                            select '{"status":"error","response":"An error occured while linking student to school","pointer":"{$locator}"}' as response;
                                                            rollback;
                                                            leave `inner_process`;
                                                        end if;
                                                    end if;
query;

                                                if(is_numeric(array_search('image',$studentItem[$mode],true))){
                                                    $locator = str_replace('.','-->',"{$prefix}.image");
                                                    $coverImage = $uploader->getFileDataFor("{$locator}-->tmp_name");
                                                    if($coverImage){
                                                        $finfo = \finfo_open(FILEINFO_MIME_TYPE);
                                                        $mime = \finfo_file($finfo,$coverImage);

                                                        if(preg_match('#^image\/.*$#',$mime)){
                                                            $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');
                                                            $value = $db->quote('"cover-image":'.substr($db->quote($mainCoverImage),1,-1).'"');

                                                            if($mainCoverImage){
                                                                $imageLocations[] = $mainCoverImage;
                                                                $query .= <<<"query"
                                                                    update secondary_schools set _data = concat(_data,',',{$value}) where id = schoolId;

                                                                    if not row_count() then
                                                                        select '{"status":"error","response":"An error occured while setting school image","pointer":"{$prefix}.image","isPicture":true}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    end if;
query;
                                                            }else{
                                                                unlink($coverImage);
                                                            }
                                                        }else{
                                                            unlink($coverImage);
                                                        }
                                                    }
                                                }

                                                foreach($studentItem[$mode] as $schoolKey => &$schoolItem){
                                                    if(!is_array($schoolItem)){
                                                        if(isset(array_flip([
                                                            'image'
                                                        ])[$schoolItem])){
                                                            continue;
                                                        }

                                                        switch($schoolItem){
                                                            case 'name':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                $validatorRules = [
                                                                    $key => 'required|min_len,3|max_len,255'
                                                                ];

                                                                $filterRules = [
                                                                    $key => 'trim|sanitize_string|lower_case'
                                                                ];

                                                                $validator->validation_rules($validatorRules);
                                                                $validator->filter_rules($filterRules);
                                                                $validatedData = $validator->run([
                                                                    $key => $value
                                                                ]);

                                                                if($validatedData === false){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => "PLease insert a valid school name"
                                                                    ];
                                                                }

                                                                $item = $db->quote($validatedData[$key]);
                                                                $query .= <<<"query"
                                                                    update secondary_schools set _name = {$item} where id = schoolId;

                                                                    if not row_count() then
                                                                        select '{"status":"error","response":"An error occured while setting school name","pointer":"{$locator}"}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    end if;
query;
                                                            break;

                                                            case 'uniqueName':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                $validatorRules = [
                                                                    $key => 'min_len,3|max_len,255'
                                                                ];

                                                                $filterRules = [
                                                                    $key => 'trim|sanitize_string'
                                                                ];

                                                                $validator->validation_rules($validatorRules);
                                                                $validator->filter_rules($filterRules);
                                                                $validatedData = $validator->run([
                                                                    $key => $value
                                                                ]);

                                                                if($validatedData === false){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => "PLease insert a valid school unqiue name"
                                                                    ];
                                                                }

                                                                $item = $db->quote($validatedData[$key]);
                                                                $query .= <<<"query"
                                                                    if (select 1 from secondary_schools where _unique_name = {$item}) then
                                                                        select '{"status":"error","response":"School unique name already exists","pointer":"{$locator}"}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    else
                                                                        update secondary_schools set _unique_name = {$item} where id = schoolId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting school unique name","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
                                                                    end if;
query;
                                                            break;

                                                            case 'country':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                                if(!($value && is_numeric($value))){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => "PLease select a valid school country"
                                                                    ];
                                                                }

                                                                $item = $db->quote($value);
                                                                $query .= <<<"query"
                                                                    if not (select 1 from countries where id = {$item} limit 1) then
                                                                        select '{"status":"error","response":"Invalid school country selected","pointer":"{$locator}"}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    else
                                                                        update secondary_schools set _country_id = {$item} where id = schoolId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting school country","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
                                                                    end if;
query;
                                                            break;

                                                            case 'region':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                                if(!($value && is_numeric($value))){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => "PLease select a valid school region"
                                                                    ];
                                                                }

                                                                $item = $db->quote($value);
                                                                $query .= <<<"query"
                                                                    if not (select 1 from regions where id = {$item} limit 1) then
                                                                        select '{"status":"error","response":"Invalid school region selected","pointer":"{$locator}"}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    else
                                                                        update secondary_schools set _region_id = {$item} where id = schoolId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting school region","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
                                                                    end if;
query;
                                                            break;

                                                            case 'lga':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                                if(!($value && is_numeric($value))){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => "PLease select a valid school local government area"
                                                                    ];
                                                                }

                                                                $item = $db->quote($value);
                                                                $query .= <<<"query"
                                                                    if not (select 1 from lgas where id = {$item} limit 1) then
                                                                        select '{"status":"error","response":"Invalid school lga selected","pointer":"{$locator}"}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    else
                                                                        update secondary_schools set _lga_id = {$item} where id = schoolId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting school lga","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
                                                                    end if;
query;
                                                            break;

                                                            case 'isApproved':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => 'Ooops please select school approval status'
                                                                    ];
                                                                }

                                                                $item = $db->quote((int) $value);
                                                                $query .= <<<"query"
                                                                    update secondary_schools set _is_approved = {$item} where id = schoolId;

                                                                    if {$item} = 1 then
                                                                        update secondary_schools set _approved_date = now() where id = schoolId;
                                                                    end if;
query;
                                                            break;

                                                            case 'isBlocked':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => 'Ooops please select school blocked status'
                                                                    ];
                                                                }

                                                                $item = $db->quote((int) $value);
                                                                $query .= <<<"query"
                                                                    update secondary_schools set _is_blocked = {$item} where id = schoolId;
query;
                                                            break;

                                                            case 'address':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                $validatorRules = [
                                                                    $key => 'required|min_len,5|max_len,255',
                                                                ];

                                                                $filterRules = [
                                                                    $key => 'trim|sanitize_string|lower_case'
                                                                ];

                                                                $validator->validation_rules($validatorRules);
                                                                $validator->filter_rules($filterRules);
                                                                $validatedData = $validator->run([
                                                                    $key => $value
                                                                ]);

                                                                if($validatedData === false){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => "Please insert a valid school address"
                                                                    ];
                                                                }

                                                                $item = $db->quote($validatedData[$key]);
                                                                $query .= <<<"query"
                                                                    update secondary_schools set _address = {$item} where id = schoolId;

                                                                    if not row_count() then
                                                                        select '{"status":"error","response":"An error occured while setting school address","pointer":"{$locator}"}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    end if;
query;
                                                            break;

                                                            case 'description':
                                                                $key = $schoolItem;
                                                                $locator = "{$prefix}.{$key}";
                                                                $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                $validatorRules = [
                                                                    $key => 'required|min_len,5|max_len,2000',
                                                                ];

                                                                $filterRules = [
                                                                    $key => 'trim|sanitize_string|lower_case'
                                                                ];

                                                                $validator->validation_rules($validatorRules);
                                                                $validator->filter_rules($filterRules);
                                                                $validatedData = $validator->run([
                                                                    $key => $value
                                                                ]);

                                                                if($validatedData === false){
                                                                    return [
                                                                        'status' => 'error',
                                                                        'pointer' => $locator,
                                                                        'response' => "Please insert a valid school description"
                                                                    ];
                                                                }

                                                                $item = $db->quote($validatedData[$key]);
                                                                $query .= <<<"query"
                                                                    update secondary_schools set _description = {$item} where id = schoolId;

                                                                    if not row_count() then
                                                                        select '{"status":"error","response":"An error occured while setting school description","pointer":"{$locator}"}' as response;
                                                                        rollback;
                                                                        leave `inner_process`;
                                                                    end if;
query;
                                                            break;
                                                        }
                                                    }
                                                }

                                                $query .= <<<"query"
                                                    if (substr((select _data from secondary_schools where id = schoolId),1,1) = ',') then
                                                        update secondary_schools set _data = concat('{',substr(_data,2),'}') where id = schoolId;
                                                    end if;
query;
                                            break;
                                            default:
                                                return [
                                                    'status' => 'error',
                                                    'pointer' => 'student.school.mode',
                                                    'response' => "Please select a valid entry"
                                                ];
                                            break;
                                        }

                                    break;
                                }
                            }
                        }
                    break;
                    case 'admin':
                        $prefix = $checkKey;
                        $adminDatas = $this->getUtils()::getDataFromArray($data,'admin');
                        if(!is_array($adminDatas)){
                            return [
                                'status' => 'error',
                                'pointer' => "{$prefix}",
                                'response' => 'Ooops administrator data is required'
                            ];
                        }

                        foreach($adminDatas as $locKey => &$adminData){
                            if(!is_int($locKey)){
                                return [
                                    'status' => 'error',
                                    'pointer' => 'general',
                                    'response' => 'Invalid administrator data.'
                                ];
                            }

                            $prefix = "{$checkKey}.{$locKey}";
                            $adminType = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',"{$prefix}.type"));

                            if(!isset(array_flip([
                                'general-administrator',
                                'support-administrator',
                                'institution-administrator',
                                'internship-provider-administrator',
                                'secondary-school-administrator'
                            ])[$adminType])){
                                return [
                                    'status' => 'error',
                                    'pointer' => "{$prefix}",
                                    'response' => 'Invalid administrator data..'
                                ];
                            }

                            $dbName = $this->getUtils()::getDataFromArray($adminPointers,$adminType);
                            $query .= <<<"query"
                                set adminId = 0;
                                insert into administrators (_user_id,_stakeholder_id,_type) values (userId,0,'{$adminType}');
                                set adminId = last_insert_id();

                                if not adminId then
                                    select '{"status":"error","response":"An error occurred while initiating administrator","pointer":"{$prefix}","isAdmin":true}' as response;
                                    rollback;
                                    leave `inner_process`;
                                end if;
query;

                            foreach($checkItem as $adminKey => &$adminItem){
                                if(!is_array($adminItem)){
                                    switch($adminItem){
                                        case 'isApproved':
                                            $key = $adminItem;
                                            $locator = "{$prefix}.{$key}";
                                            $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                            if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                                                return [
                                                    'status' => 'error',
                                                    'pointer' => $locator,
                                                    'response' => 'Ooops please select admin approval status'
                                                ];
                                            }

                                            $item = $db->quote((int) $value);
                                            $query .= <<<"query"
                                                update administrators set _is_approved = {$item} where id = adminId;

                                                if {$item} = 1 then
                                                    update administrators set _approved_date = now() where id = adminId;
                                                end if;
query;
                                        break;
                                        case 'isBlocked':
                                            $key = $adminItem;
                                            $locator = "{$prefix}.{$key}";
                                            $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                            if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                                                return [
                                                    'status' => 'error',
                                                    'pointer' => $locator,
                                                    'response' => 'Ooops please select admin blocked status'
                                                ];
                                            }

                                            $item = $db->quote((int) $value);
                                            $query .= <<<"query"
                                                update administrators set _is_blocked = {$item} where id = adminId;
query;
                                        break;
                                    }
                                }else{
                                    switch($adminKey){
                                        case 'stakeholder':
                                            if(is_null($dbName)){
                                                continue 2;
                                            }

                                            $prefix = "{$prefix}.{$adminKey}";
                                            $locator = "{$prefix}.mode";
                                            $mode = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                            switch($mode){
                                                case 'selected':
                                                    $locator = "{$prefix}.id";
                                                    $stakeholderId = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                    if(!($stakeholderId && is_numeric($stakeholderId))){
                                                        return [
                                                            'status' => 'error',
                                                            'pointer' => $locator,
                                                            'response' => "Please select a valid stakeholder"
                                                        ];
                                                    }

                                                    $item = $db->quote($stakeholderId);
                                                    $query .= <<<"query"
                                                        if not (select 1 from {$dbName} where id = {$item} and _type = '{$adminType}' limit 1) then
                                                            select '{"status":"error","response":"Invalid stakeholder selected","pointer":"{$locator}"}' as response;
                                                            rollback;
                                                            leave `inner_process`;
                                                        end if;

                                                        set stakeholderId = {$item};
                                                        update administrators set _stakeholder_id = stakeholderId,_type = '{$adminType}' where id = adminId;

                                                        if not row_count() then
                                                            select '{"status":"error","response":"An error occured while linking stakeholder to administrator","pointer":"{$locator}"}' as response;
                                                            rollback;
                                                            leave `inner_process`;
                                                        end if;
query;
                                                break;
                                                case 'created':
                                                    if(!(isset($adminItem[$mode]) && is_array($adminItem[$mode]))){
                                                        return [
                                                            'status' => 'error',
                                                            'pointer' => 'general',
                                                            'response' => "Unknown check list for created stakeholder"
                                                        ];
                                                    }

                                                    $query .= <<<"query"
                                                        set stakeholderId = 0;
                                                        insert into {$dbName} (_unique_name,_name,_country_id,_region_id,_lga_id,_address,_data) values ('','',0,0,0,'','');
                                                        set stakeholderId = last_insert_id();

                                                        if not stakeholderId then
                                                            select '{"status":"error","response":"An error occurred while initiating stakeholder commit","pointer":"{$locator}","isStakeholder":true}' as response;
                                                            rollback;
                                                            leave `inner_process`;
                                                        else
                                                            update administrators set _stakeholder_id = stakeholderId,_type = '{$adminType}' where id = adminId;

                                                            if not row_count() then
                                                                select '{"status":"error","response":"An error occured while linking stakeholder to administrator","pointer":"{$locator}"}' as response;
                                                                rollback;
                                                                leave `inner_process`;
                                                            end if;
                                                        end if;
query;

                                                    if(is_numeric(array_search('image',$adminItem[$mode],true))){
                                                        $locator = str_replace('.','-->',"{$prefix}.image");
                                                        $coverImage = $uploader->getFileDataFor("{$locator}-->tmp_name");
                                                        if($coverImage){
                                                            $finfo = \finfo_open(FILEINFO_MIME_TYPE);
                                                            $mime = \finfo_file($finfo,$coverImage);

                                                            if(preg_match('#^image\/.*$#',$mime)){
                                                                $mainCoverImage = $uploader->moveTo($coverImage,$this->getUtils()::getDataFromArray($this->managers,'adminSettings-->documentRoot').'/public/assets/cover-images/');
                                                                $value = $db->quote('"cover-image":'.substr($db->quote($mainCoverImage),1,-1).'"');

                                                                if($mainCoverImage){
                                                                    $imageLocations[] = $mainCoverImage;
                                                                    $query .= <<<"query"
                                                                        update {$dbName} set _data = concat(_data,',',{$value}) where id = stakeholderId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting stakeholder image","pointer":"{$prefix}.image","isPicture":true}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
query;
                                                                }else{
                                                                    unlink($coverImage);
                                                                }
                                                            }else{
                                                                unlink($coverImage);
                                                            }
                                                        }
                                                    }

                                                    foreach($adminItem[$mode] as $stakeholderKey => &$stakeholderItem){
                                                        if(!is_array($stakeholderItem)){
                                                            if(isset(array_flip([
                                                                'image'
                                                            ])[$stakeholderItem])){
                                                                continue;
                                                            }

                                                            switch($stakeholderItem){
                                                                case 'name':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                    $validatorRules = [
                                                                        $key => 'required|min_len,3|max_len,255'
                                                                    ];

                                                                    $filterRules = [
                                                                        $key => 'trim|sanitize_string|lower_case'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $key => $value
                                                                    ]);

                                                                    if($validatedData === false){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => "PLease insert a valid stakeholder name"
                                                                        ];
                                                                    }

                                                                    $item = $db->quote($validatedData[$key]);
                                                                    $query .= <<<"query"
                                                                        update {$dbName} set _name = {$item} where id = stakeholderId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting stakeholder name","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
query;
                                                                break;

                                                                case 'uniqueName':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                    $validatorRules = [
                                                                        $key => 'min_len,3|max_len,255'
                                                                    ];

                                                                    $filterRules = [
                                                                        $key => 'trim|sanitize_string'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $key => $value
                                                                    ]);

                                                                    if($validatedData === false){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => "Please insert a valid stakeholder unqiue name"
                                                                        ];
                                                                    }

                                                                    $item = $db->quote($validatedData[$key]);
                                                                    $query .= <<<"query"
                                                                        if (select 1 from {$dbName} where _unique_name = {$item}) then
                                                                            select '{"status":"error","response":"Stakeholder unique name already exists","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        else
                                                                            update {$dbName} set _unique_name = {$item} where id = stakeholderId;

                                                                            if not row_count() then
                                                                                select '{"status":"error","response":"An error occured while setting stakeholder unique name","pointer":"{$locator}"}' as response;
                                                                                rollback;
                                                                                leave `inner_process`;
                                                                            end if;
                                                                        end if;
query;
                                                                break;

                                                                case 'country':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                                    if(!($value && is_numeric($value))){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => "PLease select a valid stakeholder country"
                                                                        ];
                                                                    }

                                                                    $item = $db->quote($value);
                                                                    $query .= <<<"query"
                                                                        if not (select 1 from countries where id = {$item} limit 1) then
                                                                            select '{"status":"error","response":"Invalid stakeholder country selected","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        else
                                                                            update {$dbName} set _country_id = {$item} where id = stakeholderId;

                                                                            if not row_count() then
                                                                                select '{"status":"error","response":"An error occured while setting stakeholder country","pointer":"{$locator}"}' as response;
                                                                                rollback;
                                                                                leave `inner_process`;
                                                                            end if;
                                                                        end if;
query;
                                                                break;

                                                                case 'region':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                                    if(!($value && is_numeric($value))){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => "PLease select a valid stakeholder region"
                                                                        ];
                                                                    }

                                                                    $item = $db->quote($value);
                                                                    $query .= <<<"query"
                                                                        if not (select 1 from regions where id = {$item} limit 1) then
                                                                            select '{"status":"error","response":"Invalid stakeholder region selected","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        else
                                                                            update {$dbName} set _region_id = {$item} where id = stakeholderId;

                                                                            if not row_count() then
                                                                                select '{"status":"error","response":"An error occured while setting stakeholder region","pointer":"{$locator}"}' as response;
                                                                                rollback;
                                                                                leave `inner_process`;
                                                                            end if;
                                                                        end if;
query;
                                                                break;

                                                                case 'lga':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()->getDataOfHash($this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator)));

                                                                    if(!($value && is_numeric($value))){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => "PLease select a valid stakeholder local government area"
                                                                        ];
                                                                    }

                                                                    $item = $db->quote($value);
                                                                    $query .= <<<"query"
                                                                        if not (select 1 from lgas where id = {$item} limit 1) then
                                                                            select '{"status":"error","response":"Invalid stakeholder lga selected","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        else
                                                                            update {$dbName} set _lga_id = {$item} where id = stakeholderId;

                                                                            if not row_count() then
                                                                                select '{"status":"error","response":"An error occured while setting stakeholder lga","pointer":"{$locator}"}' as response;
                                                                                rollback;
                                                                                leave `inner_process`;
                                                                            end if;
                                                                        end if;
query;
                                                                break;

                                                                case 'isApproved':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                    if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => 'Ooops please select stakeholder approval status'
                                                                        ];
                                                                    }

                                                                    $item = $db->quote((int) $value);
                                                                    $query .= <<<"query"
                                                                        update {$dbName} set _is_approved = {$item} where id = stakeholderId;

                                                                        if {$item} = 1 then
                                                                            update {$dbName} set _approved_date = now() where id = stakeholderId;
                                                                        end if;
query;
                                                                break;

                                                                case 'isBlocked':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                    if(!(!is_null($value) && in_array((int) $value,[1,0],true))){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => 'Ooops please select stakeholder blocked status'
                                                                        ];
                                                                    }

                                                                    $item = $db->quote((int) $value);
                                                                    $query .= <<<"query"
                                                                        update {$dbName} set _is_blocked = {$item} where id = stakeholderId;
query;
                                                                break;

                                                                case 'address':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                    $validatorRules = [
                                                                        $key => 'required|min_len,5|max_len,255',
                                                                    ];

                                                                    $filterRules = [
                                                                        $key => 'trim|sanitize_string|lower_case'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $key => $value
                                                                    ]);

                                                                    if($validatedData === false){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => "Please insert a valid stakeholder address"
                                                                        ];
                                                                    }

                                                                    $item = $db->quote($validatedData[$key]);
                                                                    $query .= <<<"query"
                                                                        update {$dbName} set _address = {$item} where id = stakeholderId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting stakeholder address","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
query;
                                                                break;

                                                                case 'description':
                                                                    $key = $stakeholderItem;
                                                                    $locator = "{$prefix}.{$key}";
                                                                    $value = $this->getUtils()::getDataFromArray($data,str_replace('.','-->',$locator));

                                                                    $validatorRules = [
                                                                        $key => 'required|min_len,5|max_len,2000',
                                                                    ];

                                                                    $filterRules = [
                                                                        $key => 'trim|sanitize_string|lower_case'
                                                                    ];

                                                                    $validator->validation_rules($validatorRules);
                                                                    $validator->filter_rules($filterRules);
                                                                    $validatedData = $validator->run([
                                                                        $key => $value
                                                                    ]);

                                                                    if($validatedData === false){
                                                                        return [
                                                                            'status' => 'error',
                                                                            'pointer' => $locator,
                                                                            'response' => "Please insert a valid stakeholder description"
                                                                        ];
                                                                    }

                                                                    $item = $db->quote($validatedData[$key]);
                                                                    $query .= <<<"query"
                                                                        update {$dbName} set _description = {$item} where id = stakeholderId;

                                                                        if not row_count() then
                                                                            select '{"status":"error","response":"An error occured while setting stakeholder description","pointer":"{$locator}"}' as response;
                                                                            rollback;
                                                                            leave `inner_process`;
                                                                        end if;
query;
                                                                break;
                                                            }
                                                        }
                                                    }

                                                    $query .= <<<"query"
                                                        if (substr((select _data from {$dbName} where id = stakeholderId),1,1) = ',') then
                                                            update {$dbName} set _data = concat('{',substr(_data,2),'}') where id = stakeholderId;
                                                        end if;
query;
                                                break;
                                            }
                                        break;
                                    }
                                }
                            }
                        }
                    break;
                }
            }
        }

        $query .= <<<"query"
                    if (substr((select _data from users where id = userId),1,1) = ',') then
                        update users set _data = concat('{',substr(_data,2),'}') where id = userId;
                    end if;

                    commit;
                    select '{"status":"ok","response":"User added succesfully"}' as response;
                    leave `inner_process`;
                end;
            end;
query;

        $result = $db->query($query)->result(false);
        if($result){
            $result = json_decode($result[0]['response'],true);
            if(is_array($result)){
                return $result;
            }
            return $this->getUtils()::getResponseFor('malformed-db-response');
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function get(string $type,$data = []){
        $db = $this->getDatabase();
        $fetch = \Closure::bind(function($localData,$forcedData = []) use (&$data,&$db){
            $filtersQuery = '';
            $ordersQuery = '';

            $filters = $this->getUtils()::getDataFromArray($data,'filters') ?: [];
            $orders = $this->getUtils()::getDataFromArray($data,'orders') ?: [];
            $query = $this->getUtils()::getDataFromArray($localData,'query');
            $checkList = $this->getUtils()::getDataFromArray($localData,'checkList') ?: [];
            $glue = $this->getUtils()::getDataFromArray($localData,'glue') ?: '(1 = 1)';

            $compareUsing = [
                'equality',
                'like',
                'greater than',
                'less than',
                'between',
                'not between'
            ];

            foreach($filters as $filter){
                if(is_array($filter) && isset($filter['pointer']) && isset($filter['value']) && isset($filter['compareUsing']) && isset(array_flip($compareUsing)[$filter['compareUsing']]) && isset($checkList[$filter['pointer']])){
                    $pointer = $filter['pointer'];
                    $value = $filter['value'];
                    if(is_string($value)){
                        $value = $db->quote($value);
                    }
                    $compareWith = $filter['compareUsing'];
                    $inject = $checkList[$pointer];
                    if(strpos(strtolower($pointer),'date') !== false){
                        if(is_array($value)){
                            foreach($value as &$va){
                                if(is_string($va)){
                                    $va = strtotime($va) ?: time();
                                    $va = $db->quote(date('Y-m-d H:i:s',$va));
                                }else{
                                    $va = time();
                                    $va = $db->quote(date('Y-m-d H:i:s',$va));
                                }
                                $va = "str_to_date({$va},'%Y-%m-%d %H:%i:%s')";
                            }
                        }else{
                            if(is_string($value)){
                                $value = strtotime($value) ?: time();
                                $value = $db->quote(date('Y-m-d H:i:s',$value));
                            }else{
                                $value = time();
                                $value = $db->quote(date('Y-m-d H:i:s',$value));
                            }
                            $value = "str_to_date({$value},'%Y-%m-%d %H:%i:%s')";
                        }
                    }

                    switch($compareWith){
                        case 'equality':
                            $filtersQuery .= " and {$inject} = {$value}";
                        break;

                        case 'like':
                            $filtersQuery .= " and {$inject} like concat('%',{$value},'%')";
                        break;

                        case 'greater than':
                            $filtersQuery .= " and {$inject} > {$value}";
                        break;

                        case 'less than':
                            $filtersQuery .= " and {$inject} < {$value}";
                        break;

                        case 'between':
                            if(is_array($value) && isset($value[0]) && (count($value) === 2)){
                                $filtersQuery .= " and ({$inject} between {$value[0]} and {$value[1]})";
                            }
                        break;

                        case 'not between':
                            if(is_array($value) && isset($value[0]) && (count($value) === 2)){
                                $filtersQuery .= " and ({$inject} not between {$value[0]} and {$value[1]})";
                            }
                        break;
                    }
                }
            }

            foreach($orders as $order){
                if(is_array($order) && isset($order['pointer']) && isset($order['type']) && isset(array_flip([
                    'asc',
                    'desc'
                ])[$order['type']]) && isset($checkList[$order['pointer']])){
                    $pointer = $order['pointer'];
                    $value = $order['type'];
                    $inject = $checkList[$pointer];

                    $prepend = strlen($ordersQuery) ? ',' : ' order by ';
                    $ordersQuery .= "{$prepend}{$inject} {$value}";
                }
            }

            $from = ((int)$this->getUtils()::getDataFromArray($data,'from') ?: 0);
            $limit = ((int)$this->getUtils()::getDataFromArray($data,'limit') ?: 50);

            $limitQuery = "limit {$limit} offset {$from}";

            $forceSingleResult = (bool) $this->getUtils()::getDataFromArray($forcedData,'ensureSingleResult');

            if($forceSingleResult){
                $limitQuery = 'limit 1';
            }

            $result = $db->query("{$query} where ({$glue}{$filtersQuery}){$ordersQuery} {$limitQuery}")->result(true);
            return $result;
        },$this);

        switch($type){
            case 'user-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from users as user where user.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();

                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'stakeholder-portfolio-data':
                $stakeholderId = $this->getUtils()::getDataFromArray($data,'stakeholderId');
                $stakeholderType = $this->getUtils()::getDataFromArray($data,'stakeholderType');
                $tableName = str_replace('-','_',$stakeholderType.'s');

                $query = <<<"query"
                    begin not atomic
                        `inner_process`: begin
                            declare id int;
                            declare name varchar(255);
                            declare uniqueName varchar(255);
                            declare countryId int;
                            declare countryName varchar(255);
                            declare regionId int;
                            declare regionName varchar(255);
                            declare lgaId int;
                            declare lgaName varchar(255);
                            declare type varchar(255);
                            declare description longtext;
                            declare address varchar(255);
                            declare isApproved tinyint;
                            declare isBlocked tinyint;
                            declare approvalRequestDate timestamp;
                            declare approvedDate timestamp;
                            declare addedBy int;
                            declare data longtext;
                            declare userData longtext;
                            declare currUserData longtext;
                            declare currAdminId int default 0;
                            declare totalAdmin int default 0;

                            set type = '{$stakeholderType}';
                            set id = {$stakeholderId};

                            select stakeholder.id,stakeholder._name,stakeholder._unique_name,stakeholder._country_id,stakeholderCountry._name,stakeholder._region_id,stakeholderRegion._name,stakeholder._lga_id,stakeholderLga._name,stakeholder._address,stakeholder._description,stakeholder._is_approved,stakeholder._is_blocked,stakeholder._approval_request_date,stakeholder._approved_date,stakeholder._added_by,stakeholder._data into id,name,uniqueName,countryId,countryName,regionId,regionName,lgaId,lgaName,address,description,isApproved,isBlocked,approvalRequestDate,approvedDate,addedBy,data from {$tableName} as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id) where stakeholder.id = id limit 1;

                            if (char_length(name) and id) then
                                select count(admin.id) into totalAdmin from administrators as admin where admin._stakeholder_id = id;
                                set userData = '[';

                                if totalAdmin then
                                    set currAdminId = 0;

                                    `out_while`: while totalAdmin do
                                        set totalAdmin = (totalAdmin - 1),currUserData = '';

                                        select admin.id,concat('{"id":"',ifnull(user.id,''),'","name":"',ifnull(user._name,''),'","email":"',ifnull(user._email,''),'","phoneNumber":"',ifnull(user._phone_number,''),'","country":{"id":"',ifnull(user._country_id,''),'","name":"',ifnull(userCountry._name,''),'"},"region":{"id":"',ifnull(user._region_id,''),'","name":"',ifnull(userRegion._name,''),'"},"lga":{"id":"',ifnull(user._lga_id,''),'","name":"',ifnull(userLga._name,''),'"},"type":"',ifnull(user._type,''),'","token":"',ifnull(user._token,''),'","isVerified":',if(user._is_verified,1,0),',"isApproved":',if(user._is_approved,1,0),',"isBlocked":',if(user._is_blocked,1,0),',"hasCompleteRegistration":',if(user._has_complete_registration,1,0),',"registerDate":"',ifnull(user._date,''),'","data":',ifnull(user._data,'{}'),',"admin":{"id":"',ifnull(admin.id,''),'","type":"',ifnull(admin._type,''),'","isApproved":',if(admin._is_approved,1,0),',"isBlocked":',if(admin._is_blocked,1,0),',"approvalRequestDate":"',ifnull(admin._approval_request_date,''),'","approvedDate":"',ifnull(admin._approved_date,''),'","data":',ifnull(admin._data,'{}'),'}','}') into currAdminId,currUserData from administrators as admin join users as user on (admin._user_id = user.id) join countries as userCountry on (user._country_id = userCountry.id) join regions as userRegion on (user._region_id = userRegion.id) join lgas as userLga on (user._lga_id = userLga.id) where admin.id > currAdminId and admin._stakeholder_id = id order by admin.id asc limit 1;

                                        if (currAdminId) then
                                            set userData = concat(userData,if(userData = '[','',','),currUserData);
                                        end if;
                                    end while `out_while`;
                                end if;

                                set userData = concat(userData,']');
                                select concat('{"status":"ok","response":',concat('{"id":"',id,'","name":"',ifnull(name,''),'","uniqueName":"',ifnull(uniqueName,''),'","country":{"id":"',ifnull(countryId,''),'","name":"',ifnull(countryName,''),'"},"region":{"id":"',ifnull(regionId,''),'","name":"',ifnull(regionName,''),'"},"lga":{"id":"',ifnull(lgaId,''),'","name":"',ifnull(lgaName,''),'"},"type":"',ifnull(type,''),'","description":"',ifnull(description,''),'","address":"',ifnull(address,''),'","isApproved":',if(isApproved,1,0),',"isBlocked":',if(isBlocked,1,0),',"approvalRequestDate":"',ifnull(approvalRequestDate,''),'","approvedDate":"',ifnull(approvedDate,''),'","addedBy":"',ifnull(addedBy,''),'","data":',ifnull(data,'{}'),',"adminProfiles":',ifnull(userData,'null'),'}'),'}') as response;
                                leave `inner_process`;
                            else
                                select '{"status":"error","response":"Invalid stakeholder"}' as response;
                                leave `inner_process`;
                            end if;
                        end;
                    end;
query;
                $result = $db->query($query)->result();
                if(is_array($result)){
                    $result = json_decode($result[0]['response'],true);

                    if(!is_array($result)){
                        return $this->getUtils()::getResponseFor('malformed-db-response');
                    }

                    if($result['status'] == 'ok'){
                        $this->getUtils()::setDataInArray($result,'response-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->id')));
                        $this->getUtils()::setDataInArray($result,'response-->country-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->country-->id')));
                        $this->getUtils()::setDataInArray($result,'response-->region-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->region-->id')));
                        $this->getUtils()::setDataInArray($result,'response-->lga-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->lga-->id')));

                        $image = $this->getUtils()::getDataFromArray($result,'response-->data-->cover-image');
                        if($image){
                            $coverImageList = explode('/assets/',$image);
                            $this->getUtils()::setDataInArray($result,'response-->data-->cover-image',"/assets/{$coverImageList[1]}");
                        }else{
                            $this->getUtils()::setDataInArray($result,'response-->data-->cover-image',null);
                        }

                        if(is_array($this->getUtils()::getDataFromArray($result,'response-->adminProfiles'))){
                            foreach(array_keys($this->getUtils()::getDataFromArray($result,'response-->adminProfiles')) as $key){
                                $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->id")));
                                $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->country-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->country-->id")));
                                $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->region-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->region-->id")));
                                $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->lga-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->lga-->id")));

                                $image = $this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->data-->cover-image");
                                if($image){
                                    $coverImageList = explode('/assets/',$image);
                                    $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->data-->cover-image","/assets/{$coverImageList[1]}");
                                }else{
                                    $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->data-->cover-image",null);
                                }

                                $stateOfResidenceId = $this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->data-->stateOfResidence-->id");

                                if(is_numeric($stateOfResidenceId)){
                                    $stateData = $db->query("select _name as name,id as id from regions where id = {$stateOfResidenceId} limit 1")->result();
                                    $this->getUtils()::removeDataFromArray($result,"response-->adminProfiles-->{$key}-->data-->stateOfResidence");
                                    if($stateData){
                                        $stateName = $stateData[0]['name'];
                                        $stateId = $this->getUtils()->getHashOfData($stateData[0]['id']);

                                        $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->data-->stateOfResidence-->id",$stateId);
                                        $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->data-->stateOfResidence-->name",$stateName);
                                    }
                                }

                                if(is_array($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->admin"))){
                                    $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->admin-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->admin-->id")));
                                }
                            }
                        }
                    }
                    return $result;
                }else{
                    return $this->getUtils()::getResponseFor('invalid-db-response');
                }
            break;

            case 'user-portfolio-data':
                $filter = $this->getUtils()::getDataFromArray($data,'filter');
                $userId = 0;

                $dataQuery = <<<'query'
                    select user.id,user._name,user._email,user._phone_number,user._unique_name,user._country_id,userCountry._name,user._region_id,userRegion._name,user._lga_id,userLga._name,user._is_verified,user._is_approved,user._is_blocked,user._has_complete_registration,user._date,user._data,user._type,user._token into id,name,email,phone,uniqueName,countryId,countryName,regionId,regionName,lgaId,lgaName,isVerified,isApproved,isBlocked,hasCompleteRegistration,registeredDate,data,userType,userToken from users as user left join countries as userCountry on (user._country_id = userCountry.id) left join regions as userRegion on (user._region_id = userRegion.id) left join lgas as userLga on (user._lga_id = userLga.id)
query;

                if(is_array($filter)){
                    $use = $this->getUtils()::getDataFromArray($filter,'use');
                    $isChanged = 0;

                    if(isset(array_flip([
                        'id',
                        'passphrase'
                    ])[$use])){
                        switch($use){
                            case 'passphrase':
                                $login = $this->getUtils()::getDataFromArray($filter,'login');
                                $password = $this->getUtils()::getDataFromArray($filter,'password');

                                if($login && $password){
                                    $usersManager = $this->getUtils()->init('Users-Manager');
                                    $login = $db->quote($login);
                                    $password = $db->quote($usersManager->generateHashedPassword($password));
                                    $recursiveQuery = <<<'query'
                                        select user.id as id,user._name as _name,user._email as _email,user._phone_number as _phone_number,user._unique_name as _unique_name,user._country_id as _country_id,userCountry._name as _country_name,user._region_id as _region_id,userRegion._name as _region_name,user._lga_id as _lga_id,userLga._name as _lga_name,user._is_verified as _is_verified,user._is_approved as _is_approved,user._is_blocked as _is_blocked,user._has_complete_registration as _has_complete_registration,user._date as _date,user._data as _data,user._type as _type,user._token as _token from users as user left join countries as userCountry on (user._country_id = userCountry.id) left join regions as userRegion on (user._region_id = userRegion.id) left join lgas as userLga on (user._lga_id = userLga.id)
query;
                                    $recursiveQuery = <<<"query"
                                        {$recursiveQuery} where _email = {$login} and _password = {$password} union {$recursiveQuery} where _unique_name = {$login} and _password = {$password} union {$recursiveQuery} where _phone_number = {$login} and _password = {$password} limit 1
query;
                                    $dataQuery = <<<"query"
                                        select user.id,user._name,user._email,user._phone_number,user._unique_name,user._country_id,user._country_name,user._region_id,user._region_name,user._lga_id,user._lga_name,user._is_verified,user._is_approved,user._is_blocked,user._has_complete_registration,user._date,user._data,user._type,user._token into id,name,email,phone,uniqueName,countryId,countryName,regionId,regionName,lgaId,lgaName,isVerified,isApproved,isBlocked,hasCompleteRegistration,registeredDate,data,userType,userToken from ($recursiveQuery) as user limit 1;
query;
                                    $isChanged = 1;
                                }
                            break;

                            case 'id':
                                $id = $this->getUtils()::getDataFromArray($filter,'id');

                                if($id){
                                    $id = $db->quote($id);
                                    $dataQuery = <<<"query"
                                        {$dataQuery} where user.id = {$id} limit 1;
query;
                                    $isChanged = 1;
                                }
                            break;
                        }
                    }

                    if(!$isChanged){
                        return [
                            'status' => 'error',
                            'response' => 'Ooops user does not exists'
                        ];
                    }
                }else{
                    $userId = $this->getUtils()::getDataFromArray($data,'userId');

                    if(!$userId){
                        $userId = $this->getSession()->get('userData-->id');
                    }

                    $dataQuery = <<<"query"
                        {$dataQuery} where user.id = {$db->quote($userId)} limit 1;
query;
                }

                $result;
                if(!$userId || ($userId != $this->getSession()->get('userData-->id'))){
                    $query = <<<"query"
                        begin not atomic
                            `inner_process`: begin
                                declare id int;
                                declare name varchar(255);
                                declare email varchar(255);
                                declare phone varchar(15);
                                declare uniqueName varchar(255);
                                declare countryId int;
                                declare countryName varchar(255);
                                declare regionId int;
                                declare regionName varchar(255);
                                declare lgaId int;
                                declare lgaName varchar(255);
                                declare userType varchar(255);
                                declare userToken varchar(255);
                                declare isVerified tinyint;
                                declare isApproved tinyint;
                                declare isBlocked tinyint;
                                declare hasCompleteRegistration tinyint;
                                declare registeredDate timestamp;
                                declare data longtext;
                                declare adminData longtext;
                                declare currAdminData longtext;
                                declare currAdminId int default 0;
                                declare totalAdmin int default 0;
                                declare currStakeholderId int default 0;
                                declare currAdminType longtext;
                                declare currAdminIsApproved tinyint default 0;
                                declare currAdminIsBlocked tinyint default 0;
                                declare currAdminApprovalRequestDate longtext;
                                declare currAdminApprovedDate longtext;
                                declare currAdminSavedData longtext;
                                declare studentData longtext;

                                set data = '';
                                {$dataQuery}

                                if (char_length(name) and id) then
                                    case userType
                                        when 'student' then
                                            select concat('{"id":"',ifnull(student.id,''),'","level":"',ifnull(student._level,''),'","dob":"',ifnull(student._dob,''),'","isApproved":',if(student._is_approved,1,0),',"isBlocked":',if(student._is_blocked,1,0),',"approvalRequestDate":"',ifnull(student._approval_request_date,''),'","approvedDate":"',ifnull(student._approved_date,''),'","data":',ifnull(student._data,'{}'),',"school":{"id":"',ifnull(stakeholder.id,''),'","name":"',ifnull(stakeholder._name,''),'","uniqueName":"',ifnull(stakeholder._unique_name,''),'","country":{"name":"',ifnull(stakeholderCountry._name,''),'","id":"',ifnull(stakeholder._country_id,''),'"},"region":{"name":"',ifnull(stakeholderRegion._name,''),'","id":"',ifnull(stakeholder._region_id,''),'"},"lga":{"name":"',ifnull(stakeholderLga._name,''),'","id":"',ifnull(stakeholder._lga_id,''),'"},"address":"',ifnull(stakeholder._address,''),'","description":"',ifnull(stakeholder._description,''),'","isApproved":',if(stakeholder._is_approved,1,0),',"isBlocked":',if(stakeholder._is_blocked,1,0),',"approvalRequestDate":"',ifnull(stakeholder._approval_request_date,''),'","approvedDate":"',ifnull(stakeholder._approved_date,''),'","data":',ifnull(stakeholder._data,'{}'),'}}') into studentData from students as student left join secondary_schools as stakeholder on (student._secondary_school_id = stakeholder.id) join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id) where student._user_id = id limit 1;
                                        else
                                            select count(admin.id) into totalAdmin from administrators as admin where admin._user_id = id;
                                            set adminData = '[';
                                            if totalAdmin then
                                                `out_while`: while totalAdmin do
                                                    set totalAdmin = (totalAdmin - 1),currAdminType = '',currStakeholderId = 0,currAdminData = '',currAdminIsApproved = 0,currAdminIsBlocked = 0,currAdminApprovalRequestDate = '',currAdminApprovedDate = '',currAdminSavedData = '';
                                                    select admin._type,admin.id,admin._stakeholder_id,if(admin._is_approved,1,0),if(admin._is_blocked,1,0),ifnull(admin._approval_request_date,''),ifnull(admin._approved_date,''),ifnull(admin._data,'{}') into currAdminType,currAdminId,currStakeholderId,currAdminIsApproved,currAdminIsBlocked,currAdminApprovalRequestDate,currAdminApprovedDate,currAdminSavedData from administrators as admin where admin.id > currAdminId and admin._user_id = id order by admin.id asc limit 1;

                                                    if (char_length(currAdminType) and currAdminId) then
                                                        case currAdminType
                                                            when 'institution-administrator' then
                                                                select concat('{"id":"',currAdminId,'","type":"',currAdminType,'","isApproved":',if(currAdminIsApproved,1,0),',"isBlocked":',if(currAdminIsBlocked,1,0),',"approvalRequestDate":"',currAdminApprovalRequestDate,'","approvedDate":"',currAdminApprovedDate,'","data":',currAdminSavedData,',"stakeholder":{"id":"',ifnull(stakeholder.id,''),'","name":"',ifnull(stakeholder._name,''),'","uniqueName":"',ifnull(stakeholder._unique_name,''),'","country":{"name":"',ifnull(stakeholderCountry._name,''),'","id":"',ifnull(stakeholder._country_id,''),'"},"region":{"name":"',ifnull(stakeholderRegion._name,''),'","id":"',ifnull(stakeholder._region_id,''),'"},"lga":{"name":"',ifnull(stakeholderLga._name,''),'","id":"',ifnull(stakeholder._lga_id,''),'"},"address":"',ifnull(stakeholder._address,''),'","description":"',ifnull(stakeholder._description,''),'","isApproved":',if(stakeholder._is_approved,1,0),',"isBlocked":',if(stakeholder._is_blocked,1,0),',"approvalRequestDate":"',ifnull(stakeholder._approval_request_date,''),'","approvedDate":"',ifnull(stakeholder._approved_date,''),'","data":',ifnull(stakeholder._data,'{}'),'}}') into currAdminData from institutions as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id) where stakeholder.id = currStakeholderId limit 1;
                                                            when 'internship-provider-administrator' then
                                                                select concat('{"id":"',currAdminId,'","type":"',currAdminType,'","isApproved":',if(currAdminIsApproved,1,0),',"isBlocked":',if(currAdminIsBlocked,1,0),',"approvalRequestDate":"',currAdminApprovalRequestDate,'","approvedDate":"',currAdminApprovedDate,'","data":',currAdminSavedData,',"stakeholder":{"id":"',ifnull(stakeholder.id,''),'","name":"',ifnull(stakeholder._name,''),'","uniqueName":"',ifnull(stakeholder._unique_name,''),'","country":{"name":"',ifnull(stakeholderCountry._name,''),'","id":"',ifnull(stakeholder._country_id,''),'"},"region":{"name":"',ifnull(stakeholderRegion._name,''),'","id":"',ifnull(stakeholder._region_id,''),'"},"lga":{"name":"',ifnull(stakeholderLga._name,''),'","id":"',ifnull(stakeholder._lga_id,''),'"},"address":"',ifnull(stakeholder._address,''),'","description":"',ifnull(stakeholder._description,''),'","isApproved":',if(stakeholder._is_approved,1,0),',"isBlocked":',if(stakeholder._is_blocked,1,0),',"approvalRequestDate":"',ifnull(stakeholder._approval_request_date,''),'","approvedDate":"',ifnull(stakeholder._approved_date,''),'","data":',ifnull(stakeholder._data,'{}'),'}}') into currAdminData from internship_providers as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id) where stakeholder.id = currStakeholderId limit 1;
                                                            when 'secondary-school-administrator' then
                                                                select concat('{"id":"',currAdminId,'","type":"',currAdminType,'","isApproved":',if(currAdminIsApproved,1,0),',"isBlocked":',if(currAdminIsBlocked,1,0),',"approvalRequestDate":"',currAdminApprovalRequestDate,'","approvedDate":"',currAdminApprovedDate,'","data":',currAdminSavedData,',"stakeholder":{"id":"',ifnull(stakeholder.id,''),'","name":"',ifnull(stakeholder._name,''),'","uniqueName":"',ifnull(stakeholder._unique_name,''),'","country":{"name":"',ifnull(stakeholderCountry._name,''),'","id":"',ifnull(stakeholder._country_id,''),'"},"region":{"name":"',ifnull(stakeholderRegion._name,''),'","id":"',ifnull(stakeholder._region_id,''),'"},"lga":{"name":"',ifnull(stakeholderLga._name,''),'","id":"',ifnull(stakeholder._lga_id,''),'"},"address":"',ifnull(stakeholder._address,''),'","description":"',ifnull(stakeholder._description,''),'","isApproved":',if(stakeholder._is_approved,1,0),',"isBlocked":',if(stakeholder._is_blocked,1,0),',"approvalRequestDate":"',ifnull(stakeholder._approval_request_date,''),'","approvedDate":"',ifnull(stakeholder._approved_date,''),'","data":',ifnull(stakeholder._data,'{}'),'}}') into currAdminData from secondary_schools as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id) where stakeholder.id = currStakeholderId limit 1;
                                                            else
                                                                set currAdminData = concat('{"id":"',currAdminId,'","type":"',currAdminType,'","isApproved":',if(currAdminIsApproved,1,0),',"isBlocked":',if(currAdminIsBlocked,1,0),',"approvalRequestDate":"',currAdminApprovalRequestDate,'","approvedDate":"',currAdminApprovedDate,'","data":',currAdminSavedData,'}');
                                                        end case;

                                                        if (char_length(currAdminData)) then
                                                            set adminData = concat(adminData,if(adminData = '[','',','),currAdminData);
                                                        end if;
                                                    end if;
                                                end while `out_while`;
                                            end if;

                                            set adminData = concat(adminData,']');
                                    end case;
                                end if;

                                if (id) then
                                    select concat('{"status":"ok","response":',concat('{"id":"',id,'","name":"',ifnull(name,''),'","email":"',ifnull(email,''),'","phone":"',ifnull(phone,''),'","uniqueName":"',ifnull(uniqueName,''),'","country":{"id":"',ifnull(countryId,''),'","name":"',ifnull(countryName,''),'"},"region":{"id":"',ifnull(regionId,''),'","name":"',ifnull(regionName,''),'"},"lga":{"id":"',ifnull(lgaId,''),'","name":"',ifnull(lgaName,''),'"},"type":"',ifnull(userType,''),'","token":"',ifnull(userToken,''),'","isVerified":',if(isVerified,1,0),',"isApproved":',if(isApproved,1,0),',"isBlocked":',if(isBlocked,1,0),',"hasCompleteRegistration":',if(hasCompleteRegistration,1,0),',"registeredDate":"',ifnull(registeredDate,''),'","data":',ifnull(data,'{}'),',"adminProfiles":',ifnull(adminData,'null'),',"student":',ifnull(studentData,'null'),'}'),'}') as response;
                                else
                                    select '{"status":"error","response":"Invalid user"}' as response;
                                end if;
                                leave `inner_process`;
                            end;
                        end;
query;
                    $result = $db->query($query)->result();

                    if($result){
                        $result = json_decode($result[0]['response'],true);
                    }
                }else{
                    $result = $this->getSession()->get('userData');
                    $result = [
                        'status' => 'ok',
                        'response' => $result
                    ];
                }

                if($result){
                    if(!is_array($result)){
                        return $this->getUtils()::getResponseFor('malformed-db-response');
                    }

                    if($result['status'] == 'ok'){
                        $this->getUtils()::setDataInArray($result,'response-->rawId',$this->getUtils()::getDataFromArray($result,'response-->id'));
                        $this->getUtils()::setDataInArray($result,'response-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->id')));
                        $this->getUtils()::setDataInArray($result,'response-->country-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->country-->id')));
                        $this->getUtils()::setDataInArray($result,'response-->region-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->region-->id')));
                        $this->getUtils()::setDataInArray($result,'response-->lga-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->lga-->id')));

                        $image = $this->getUtils()::getDataFromArray($result,'response-->data-->cover-image');
                        if($image){
                            $coverImageList = explode('/assets/',$image);
                            $this->getUtils()::setDataInArray($result,'response-->data-->cover-image',"/assets/{$coverImageList[1]}");
                        }else{
                            $this->getUtils()::setDataInArray($result,'response-->data-->cover-image',null);
                        }

                        $stateOfResidenceId = $this->getUtils()::getDataFromArray($result,'response-->data-->stateOfResidence-->id');

                        if(is_numeric($stateOfResidenceId)){
                            $stateData = $db->query("select _name as name,id as id from regions where id = {$stateOfResidenceId} limit 1")->result();
                            $this->getUtils()::removeDataFromArray($result,'response-->data-->stateOfResidence');
                            if($stateData){
                                $stateName = $stateData[0]['name'];
                                $stateId = $this->getUtils()->getHashOfData($stateData[0]['id']);

                                $this->getUtils()::setDataInArray($result,'response-->data-->stateOfResidence-->id',$stateId);
                                $this->getUtils()::setDataInArray($result,'response-->data-->stateOfResidence-->name',$stateName);
                            }
                        }

                        $this->getUtils()::setDataInArray($result,'response-->canEdit',false);
                        $userManager = $this->getUtils()->init('Users-Manager');
                        if(($this->getUtils()::getDataFromArray($result,'response-->rawId') == $this->getSession()->get('userData-->id'))){
                            $this->getUtils()::setDataInArray($result,'response-->canEdit','basic');
                        }

                        if(is_array($this->getUtils()::getDataFromArray($result,'response-->adminProfiles'))){
                            foreach(array_keys($this->getUtils()::getDataFromArray($result,'response-->adminProfiles')) as $key){
                                $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->id")));

                                if(is_array($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->stakeholder"))){
                                    $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->id")));
                                    $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->country-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->country-->id")));
                                    $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->region-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->region-->id")));
                                    $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->lga-->id",$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->lga-->id")));

                                    $image = $this->getUtils()::getDataFromArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->data-->cover-image");
                                    if($image){
                                        $coverImageList = explode('/assets/',$image);
                                        $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->data-->cover-image","/assets/{$coverImageList[1]}");
                                    }else{
                                        $this->getUtils()::setDataInArray($result,"response-->adminProfiles-->{$key}-->stakeholder-->data-->cover-image",null);
                                    }
                                }
                            }
                        }

                        if(is_array($this->getUtils()::getDataFromArray($result,'response-->student'))){
                            if($userManager->hasPermissionAs('secondary-school-administrator') && ($this->getSession()->get('adminData-->secondary-school-administrator-->stakeholderId') == $this->getUtils()::getDataFromArray($result,'response-->student-->school-->id'))){
                                $this->getUtils()::setDataInArray($result,'response-->canEdit','school-all');
                            }

                            $this->getUtils()::setDataInArray($result,'response-->student-->id', $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->student-->id')));

                            if($this->getUtils()::getDataFromArray($result,'response-->student-->school-->id')){
                                $this->getUtils()::setDataInArray($result,'response-->student-->school-->id', $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->student-->school-->id')));
                                $this->getUtils()::setDataInArray($result,'response-->student-->school-->country-->id', $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->student-->school-->country-->id')));
                                $this->getUtils()::setDataInArray($result,'response-->student-->school-->region-->id', $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->student-->school-->region-->id')));
                                $this->getUtils()::setDataInArray($result,'response-->student-->school-->lga-->id', $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->student-->school-->lga-->id')));

                                $image = $this->getUtils()::getDataFromArray($result,'response-->student-->school-->data-->cover-image');
                                if($image){
                                    $coverImageList = explode('/assets/',$image);
                                    $this->getUtils()::setDataInArray($result,'response-->student-->school-->data-->cover-image',"/assets/{$coverImageList[1]}");
                                }else{
                                    $this->getUtils()::setDataInArray($result,'response-->student-->school-->data-->cover-image',null);
                                }
                            }
                        }

                        if($userManager->hasPermissionAs('general-administrator')){
                            $this->getUtils()::setDataInArray($result,'response-->canEdit','all');
                        }

                        $this->getUtils()::setDataInArray($result,'response-->controllerType',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'response-->canEdit')));
                    }

                    return $result;
                }

                return $this->getUtils()::getResponseFor('invalid-db-response');
            break;

            case 'general-administrator-dashboard-data':
                $query = <<<'query'
                    begin not atomic
                        `inner_process`: begin
                            declare totalSecondarySchools int default 0;
                            declare totalApprovedSecondarySchools int default 0;
                            declare totalBlockedSecondarySchools int default 0;
                            declare totalInternships int default 0;
                            declare totalApprovedInternships int default 0;
                            declare totalBlockedInternships int default 0;
                            declare totalStudents int default 0;
                            declare totalApprovedStudents int default 0;
                            declare totalBlockedStudents int default 0;
                            declare totalInstitutions int default 0;
                            declare totalApprovedInstitutions int default 0;
                            declare totalBlockedInstitutions int default 0;
                            declare totalCompleteTest int default 0;
                            declare totalDistinctCompleteTest int default 0;
                            declare totalAcademicTest int default 0;
                            declare totalDistinctAcademicTest int default 0;
                            declare totalTemperamentTest int default 0;
                            declare totalDistinctTemperamentTest int default 0;
                            declare eventsData longtext;
                            declare currEventData longtext;
                            declare totalNeededEvents int default 20;
                            declare totalTestYesResponse int default 0;
                            declare totalTestNoResponse int default 0;
                            declare registrationData longtext;
                            declare currRegistrationData longtext;
                            declare currData longtext;
                            declare totalRegistrationSet int default 0;
                            declare limitedPeriodSpan int default 0;
                            declare periodSpan int default 0;
                            declare registrationEndDateHolder int default 0;
                            declare registrationStartDateHolder int default 0;
                            declare currRegistrationStartDateHolder int default 0;
                            declare currRegistrationEndDateHolder int default 0;
                            declare intHolder int default 0;

                            select count(*),count(if(school._is_approved,1,null)),count(if(school._is_blocked,1,null)) into totalSecondarySchools,totalApprovedSecondarySchools,totalBlockedSecondarySchools from secondary_schools as school;

                            select count(*),count(if(internship._is_approved,1,null)),count(if(internship._is_blocked,1,null)) into totalInternships,totalApprovedInternships,totalBlockedInternships from internship_providers as internship;

                            select count(*),count(if(student._is_approved,1,null)),count(if(student._is_blocked,1,null)) into totalStudents,totalApprovedStudents,totalBlockedStudents from students as student;

                            select count(*),count(if(institution._is_approved,1,null)),count(if(institution._is_blocked,1,null)) into totalInstitutions,totalApprovedInstitutions,totalBlockedInstitutions from institutions as institution;

                            select count(test._student_id),count(distinct test._student_id) into totalCompleteTest,totalDistinctCompleteTest from students_tests as test where test._type = 'bestSubjects';

                            select count(test._student_id),count(distinct test._student_id) into totalAcademicTest,totalDistinctAcademicTest from students_tests as test where test._type = 'academic';

                            select count(test._student_id),count(distinct test._student_id) into totalTemperamentTest,totalDistinctTemperamentTest from students_tests as test where test._type = 'temperament';

                            set eventsData = null,intHolder = 0;
                            `out_while`: while (totalNeededEvents > 0) do
                                set currEventData = null;
                                select event.id,concat('{"id":"',event.id,'","name":"',event._name,'","location":"',event._location,'","date":"',event._action_date,'","coverImage":"',event._cover_image,'"}') into intHolder,currEventData from events as event where (event._action_date > now()) and event.id > intHolder order by event._action_date asc limit 1;

                                if (not isnull(currEventData)) then
                                    set eventsData = if(isnull(eventsData),concat('[',currEventData),concat(eventsData,',',currEventData)),totalNeededEvents = (totalNeededEvents - 1);
                                else
                                    set totalNeededEvents = 0;
                                    leave `out_while`;
                                end if;

                            end while `out_while`;
                            set eventsData = if(isnull(eventsData),'[]',concat(eventsData,']'));

                            select (ifnull((select count(testResponse.id) from test_survey_response_stats as testResponse where (isnull(testResponse._student_id) and (testResponse._yes = 1))),0) + ifnull((select count(testResponse.id) from test_survey_response_stats as testResponse where (testResponse._yes = 1)),0)),(ifnull((select count(testResponse.id) from test_survey_response_stats as testResponse where (isnull(testResponse._student_id) and (testResponse._no = 1))),0) + ifnull((select count(testResponse.id) from test_survey_response_stats as testResponse where (testResponse._no = 1)),0)) into totalTestYesResponse,totalTestNoResponse;

                            `reg_data`: begin
                                set periodSpan = 7,limitedPeriodSpan = (periodSpan * 6),registrationEndDateHolder = to_days(now()),registrationStartDateHolder = (registrationEndDateHolder - limitedPeriodSpan),registrationData = null,totalRegistrationSet = ceil((registrationEndDateHolder - registrationStartDateHolder) / periodSpan),currRegistrationStartDateHolder = registrationStartDateHolder,currRegistrationEndDateHolder = (currRegistrationStartDateHolder + periodSpan);

                                `out_while`: while (totalRegistrationSet > 0) do
                                    set currData = '',currRegistrationData = null;

                                    select count(user.id) into currData from users as user where user._type = 'student' and (user._date >= from_days(currRegistrationStartDateHolder)) and (user._date < from_days(currRegistrationEndDateHolder)) limit 1;

                                    if isnull(currData) then
                                        set currData = 0;
                                    end if;

                                    set currRegistrationData = if(isnull(currRegistrationData),concat('{','"students":',currData),concat(currRegistrationData,',','"students":',currData));

                                    select count(stakeholder.id) into currData from secondary_schools as stakeholder where stakeholder._is_approved = 1 and (stakeholder._approved_date >= from_days(currRegistrationStartDateHolder)) and (stakeholder._approved_date < from_days(currRegistrationEndDateHolder)) limit 1;

                                    if isnull(currData) then
                                        set currData = 0;
                                    end if;

                                    set currRegistrationData = if(isnull(currRegistrationData),concat('{','"secondary_schoools":',currData),concat(currRegistrationData,',','"secondary_schools":',currData));

                                     select count(stakeholder.id) into currData from internship_providers as stakeholder where stakeholder._is_approved = 1 and (stakeholder._approved_date >= from_days(currRegistrationStartDateHolder)) and (stakeholder._approved_date < from_days(currRegistrationEndDateHolder)) limit 1;

                                    if isnull(currData) then
                                        set currData = 0;
                                    end if;

                                    set currRegistrationData = if(isnull(currRegistrationData),concat('{','"internship_providers":',currData),concat(currRegistrationData,',','"internship_providers":',currData));

                                    set currRegistrationData = concat('{"start":"',from_days(currRegistrationStartDateHolder),'","end":"',from_days(currRegistrationEndDateHolder),'","count":',currRegistrationData,'}','}'),currRegistrationStartDateHolder = currRegistrationEndDateHolder,currRegistrationEndDateHolder = (currRegistrationEndDateHolder + periodSpan),registrationData = if(isnull(registrationData),concat('[',currRegistrationData),concat(registrationData,',',currRegistrationData));

                                    set totalRegistrationSet = (totalRegistrationSet - 1);
                                end while `out_while`;

                                set registrationData = if(isnull(registrationData),'[]',concat(registrationData,']'));
                            end;

                            select concat('{"secondarySchool":{"total":"',totalSecondarySchools,'","approved":"',totalApprovedSecondarySchools,'","blocked":"',totalBlockedSecondarySchools,'"},"internshipProvider":{"total":"',totalInternships,'","approved":"',totalApprovedInternships,'","blocked":"',totalBlockedInternships,'"},"student":{"total":"',totalStudents,'","approved":"',totalApprovedStudents,'","blocked":"',totalBlockedStudents,'"},"institution":{"total":"',totalInstitutions,'","approved":"',totalApprovedInstitutions,'","blocked":"',totalBlockedInstitutions,'"},"test":{"response":{"satisfied":{"yes":',totalTestYesResponse,',"no":',totalTestNoResponse,',"total":',(totalTestYesResponse + totalTestNoResponse),'}},"completed":"',totalCompleteTest,'","uniqueCompleted":"',totalDistinctCompleteTest,'","academic":"',totalAcademicTest,'","uniqueAcademic":"',totalDistinctAcademicTest,'","temperament":"',totalTemperamentTest,'","uniqueTemperament":"',totalDistinctTemperamentTest,'"},"events":',eventsData,',"chart":{"registration":',registrationData,'}}') as response;
                        end;
                    end;
query;

                $result = $db->query($query)->result();

                if($result){
                    $result = json_decode($result[0]['response'],true);
                    if(is_array($result)){
                        $events = &$result['events'];

                        if(is_array($events) && count($events)){
                            foreach($events as &$event){
                                $id = $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($event,'id'));
                                $this->getUtils()::setDataInArray($event,'id',$id);
                                $this->getUtils()::setDataInArray($event,'viewUrl','/event?eventId='.$id);
                                $coverImageUrl = $this->getUtils()::getDataFromArray($event,'coverImage');
                                if($coverImageUrl){
                                    $coverImageList = explode('/assets/',$coverImageUrl);
                                    $coverImageUrl = "/assets/{$coverImageList[1]}";
                                }else{
                                    $coverImageUrl = null;
                                }
                                $this->getUtils()::setDataInArray($event,'coverImage',$coverImageUrl);
                            }
                        }

                        return $result;
                    }
                }
                return [];
            break;

            case 'support-administrator-dashboard-data':

            break;

            case 'internship-provider-administrator-dashboard-data':

            break;

            case 'institution-administrator-dashboard-data':

            break;

            case 'secondary-school-administrator-dashboard-data':

            break;

            case 'student-dashboard-data':

            break;

            case 'ticket-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from tickets as ticket where ticket.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();

                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'support-ticket-categories-list':
                $localData = [
                    'query' => 'select ticketCategory.id as id,ticketCategory._name as name from tickets_categories_db as ticketCategory left join tickets_priorities_db as ticketPriority on (ticketCategory._ticket_priority_id = ticketPriority.id)',
                    'checkList' => [
                        'supportTicketCategoryApprovalStatus' => 'ticket._is_approved',
                        'supportTicketIsSeenStatus' => 'ticket._is_seen',
                        'supportTicketPriorityId' => 'ticketPriority.id',
                        'supportTicketId' => 'ticket.id',
                        'supportTicketTitle' => 'ticket._title',
                        'supportTicketDate' => 'ticket._date',
                        'supportTicketCategoryId' => 'ticketCategory.id',
                        'supportTicketCategoryName' => 'ticketCategory._name',
                        'supportTicketTotalReply' => '(select count(id) from tickets where _is_response = 1 and _ticket_id = ticket.id)',
                        'supportTicketLastReplyDate' => '(select _date from tickets where _is_response = 1 and _ticket_id = ticket.id order by id desc limit 1)'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'support-ticket-priorities-list':
                $localData = [
                    'query' => 'select ticketPriority.id as id,ticketPriority._name as name from tickets_priorities_db as ticketPriority',
                    'checkList' => [
                        'supportTicketPriorityId' => 'ticket.id',
                        'supportTicketPriorityName' => 'ticketPriority._name'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'support-ticket-data':
                $ticketId = $this->getUtils()::getDataFromArray($data,'ticketId');
                $result = $db->prepare('select ticket.id as id,ticket._title as title,ticket._from as sender,ticket._from_id as senderId,ticket._message as message,ticket._is_closed as isClosed,ticket._is_seen as isSeen,ticket._date as openDate,ticket._ticket_category_id as categoryId,ticketCategory._name as categoryName,ticketPriority.id as priorityId,ticketPriority._name as priorityName,(select _date from tickets where _is_response = 1 and _ticket_id = ticket.id order by id desc limit 1) as lastReplyDate,(select count(id) from tickets where _is_response = 1 and _ticket_id = ticket.id) as totalReplies from tickets as ticket join tickets_categories_db as ticketCategory on (ticket._ticket_category_id = ticketCategory.id) join tickets_priorities_db as ticketPriority on (ticketCategory._ticket_priority_id = ticketPriority.id) where ticket.id = :ticketId limit 1')->bind([
                    'ticketId' => $ticketId
                ])->result();

                if($result){
                    $result = $result[0];
                    $result['id'] = $this->getUtils()->getHashOfData($result['id']);
                    $result['categoryId'] = $this->getUtils()->getHashOfData($result['categoryId']);
                    $result['priorityId'] = $this->getUtils()->getHashOfData($result['priorityId']);
                    return $result;
                }

                return [];
            break;

            case 'support-ticket-replies':
                $ticketId = $this->getUtils()::getDataFromArray($data,'ticketId');
                $from = ((int) $this->getUtils()::getDataFromArray($data,'from')) ?: 0;
                $limit = ((int) $this->getUtils()::getDataFromArray($data,'limit')) ?: 20;
                $query = <<<'query'
                    begin not atomic
                        declare replies longtext;
                        declare ticketData longtext;
                        declare ticketId int default :ticketId;
                        declare _from int default :from;
                        declare _limit int default :limit;
                        declare idHolder int default 0;
                        declare currData longtext;

                        `inner_process`: begin

                            set idHolder = 0;
                            select 1,concat('{"id":',ticket.id,',"sender":{"id":',ticket._from_id,',"type":"',ticket._from,'"},"title":"',ticket._title,'","message":"',ticket._message,'","isClosed":',ticket._is_closed,',"isSeen":',ticket._is_seen,',"date":"',ticket._date,'","data":',if(isnull(ticket._data),'null',ticket._data),',"totalReplies":',(select count(id) from tickets where _is_response = 1 and _ticket_id = ticketId)) into idHolder,ticketData from tickets as ticket where ticket.id = ticketId limit 1;

                            if idHolder then

                                set replies = '[',idHolder = 0;
                                `outer_while`: while (_limit > 0) do
                                    set currData = null;

                                    if idHolder then
                                        select reply.id,concat('{"id":',reply.id,',"message":"',reply._message,'","date":"',reply._date,'","sender":{"id":',reply._from_id,',"type":"',reply._from,'"}}') into idHolder,currData from tickets as reply where reply._ticket_id = ticketId and reply._is_response = 1 and reply.id < idHolder and reply.id > _from order by reply.id desc limit 1;
                                    else
                                        select reply.id,concat('{"id":',reply.id,',"message":"',reply._message,'","date":"',reply._date,'","sender":{"id":',reply._from_id,',"type":"',reply._from,'"}}') into idHolder,currData from tickets as reply where reply._ticket_id = ticketId and reply._is_response = 1 and reply.id > 0 and reply.id > _from order by reply.id desc limit 1;
                                    end if;

                                    if (idHolder and char_length(currData)) then
                                        set replies = concat(replies,if(replies = '[','',','),currData),_limit = (_limit - 1);
                                    else
                                        set _limit = 0;
                                    end if;
                                end while `outer_while`;

                                set replies = concat(replies,']'),ticketData = concat(ticketData,',"replies":',replies,'}');

                                select ticketData as response;
                                leave `inner_process`;
                            end if;

                            select null as response;
                            leave `inner_process`;
                        end;
                    end;
query;
                $result = $db->prepare($query)->bind([
                    'ticketId' => $ticketId,
                    'from' => &$from,
                    'limit' => &$limit
                ])->result();

                if($result){
                    $result = json_decode($result[0]['response'],true);
                    if(is_array($result)){
                        $senderId = $this->getUtils()::getDataFromArray($result,'sender-->id');
                        $senderType = $this->getUtils()::getDataFromArray($result,'sender-->type');

                        $isUser = 0;
                        if($senderType === 'student'){
                            if($senderId == $this->getSession()->get('studentData-->id')){
                                $isUser = 1;
                            }
                        }else{
                            if($senderId == $this->getSession()->get("adminData-->{$senderType}-->id")){
                                $isUser = 1;
                            }
                        }

                        if($isUser){
                            $this->getUtils()::setDataInArray($result,'canEdit',1);
                            $this->getUtils()::setDataInArray($result,'canDelete',1);
                        }

                        $this->getUtils()::setDataInArray($result,'id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'id')));
                        $this->getUtils()::setDataInArray($result,'sender-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($result,'sender-->id')));
                        $this->getUtils()::setDataInArray($result,'date',date('c',strtotime($this->getUtils()::getDataFromArray($result,'date'))));

                        $data = &$result['data'];
                        if(is_array($data)){
                            $timeline = $this->getUtils()::getDataFromArray($data,'timeline');
                            if(is_array($timeline)){

                                $userManager = $this->getUtils()->init('Users-Manager');
                                forEach($timeline as &$record){
                                    if(is_array($record)){

                                        $this->getUtils()::setDataInArray($record,'date',date('c',$this->getUtils()::getDataFromArray($record,'date')));
                                        $this->getUtils()::setDataInArray($record,'by-->adminId',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($record,'by-->adminId')));
                                        $this->getUtils()::setDataInArray($record,'by-->studentId',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($record,'by-->studentId')));
                                        $this->getUtils()::setDataInArray($record,'by-->userId',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($record,'by-->userId')));

                                        $this->getUtils()::setDataInArray($result,'timeline[]',$record);
                                    }
                                }
                            }
                        }

                        $this->getUtils()::removeDataFromArray($result,'data');

                        $replies = &$result['replies'];
                        if(is_array($replies)){
                            forEach($replies as &$reply){

                                if(is_array($reply)){

                                    $replySenderId = $this->getUtils()::getDataFromArray($reply,'sender-->id');
                                    $replySenderType = $this->getUtils()::getDataFromArray($reply,'sender-->type');

                                    $this->getUtils()::setDataInArray($reply,'canDelete',0);
                                    if($userManager->hasPermissionAs('general-administrator')){
                                        $this->getUtils()::setDataInArray($reply,'canDelete',1);
                                        $this->getUtils()::setDataInArray($reply,'canEdit',1);
                                    }else{
                                        if($userManager->hasPermissionAs('support-administrator') && ($replySenderType !== 'support-administrator')){
                                            $type = $this->getUtils()::getDataFromArray($reply,'sender-->type');
                                            if($type !== 'general-administrator'){
                                                $this->getUtils()::setDataInArray($reply,'canDelete',1);
                                                $this->getUtils()::setDataInArray($reply,'canEdit',1);
                                            }
                                        }

                                        if(($replySenderId === $senderId) && ($replySenderType === $senderType)){
                                            $this->getUtils()::setDataInArray($reply,'canDelete',1);
                                            $this->getUtils()::setDataInArray($reply,'canEdit',1);
                                        }
                                    }
                                }

                                if(is_array($reply)){
                                    $this->getUtils()::setDataInArray($reply,'id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($reply,'id')));
                                    $this->getUtils()::setDataInArray($reply,'sender-->id',$this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($reply,'sender-->id')));
                                }
                            }
                        }

                        return $result;
                    }
                }

                return null;
            break;

            case 'support-tickets-count':
                $localData = [
                    'query' => 'select count(ticket.id) as totalCount from tickets as ticket join tickets_categories_db as ticketCategory on (ticket._ticket_category_id = ticketCategory.id) join tickets_priorities_db as ticketPriority on (ticketCategory._ticket_priority_id = ticketPriority.id) left join administrators as admin on (instr(ticket._from,\'administrator\') and ticket._from_id = admin.id) left join students as student on (instr(ticket._from,\'student\') and ticket._from_id = student.id) left join secondary_schools as school on (student._secondary_school_id = school.id)',
                    'checkList' => [
                        'supportTicketClosedStatus' => 'ticket._is_closed',
                        'supportTicketSeenStatus' => 'ticket._is_seen',
                        'supportTicketId' => 'ticket.id',
                        'supportTicketTitle' => 'ticket._title',
                        'supportTicketDate' => 'ticket._date',
                        'supportTicketFrom' => 'ticket._from',
                        'supportTicketFromId' => 'ticket._from_id',
                        'supportTicketAdminId' => 'admin.id',
                        'supportTicketStudentId' => 'student.id',
                        'supportTicketStudentSchoolName' => 'school._name',
                        'supportTicketStudentSchoolId' => 'school.id',
                        'supportTicketCategoryId' => 'ticketCategory.id',
                        'supportTicketCategoryName' => 'ticketCategoryName',
                        'supportTicketPriorityId' => 'ticketCategory._ticket_priority_id',
                        'supportTicketTotalReplies' => '(select count(id) from tickets where _is_response = 1 and _ticket_id = ticket.id)',
                        'supportTicketLastReplyDate' => '(select _date from tickets where _is_response = 1 and _ticket_id = ticket.id order by id desc limit 1)'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalCount'];
                }

                return 0;
            break;

            case 'support-tickets-list':
                $localData = [
                    'query' => 'select ticket.id as id,ticket._title as title,ticket._from as sender,ticket._from_id as senderId,ticket._message as message,ticket._is_closed as isClosed,ticket._is_seen as isSeen,ticket._date as openDate,ticket._ticket_category_id as categoryId,ticketCategory._name as categoryName,ticketPriority.id as priorityId,ticketPriority._name as priorityName,(select _date from tickets where _is_response = 1 and _ticket_id = ticket.id order by id desc limit 1) as lastReplyDate,(select count(id) from tickets where _is_response = 1 and _ticket_id = ticket.id) as totalReplies,auser._name as adminUserName,auser.id as adminUserId,auser._data as adminUserData,suser._name as studentUserName,suser.id as studentUserId,suser._data as studentUserData from tickets as ticket join tickets_categories_db as ticketCategory on (ticket._ticket_category_id = ticketCategory.id) join tickets_priorities_db as ticketPriority on (ticketCategory._ticket_priority_id = ticketPriority.id) left join administrators as admin on (instr(ticket._from,\'administrator\') and ticket._from_id = admin.id) left join users as auser on (admin._user_id = auser.id) left join students as student on (instr(ticket._from,\'student\') and ticket._from_id = student.id) left join users as suser on (student._user_id = suser.id) left join secondary_schools as school on (student._secondary_school_id = school.id)',
                    'checkList' => [
                        'supportTicketClosedStatus' => 'ticket._is_closed',
                        'supportTicketSeenStatus' => 'ticket._is_seen',
                        'supportTicketId' => 'ticket.id',
                        'supportTicketTitle' => 'ticket._title',
                        'supportTicketDate' => 'ticket._date',
                        'supportTicketFrom' => 'ticket._from',
                        'supportTicketFromId' => 'ticket._from_id',
                        'supportTicketAdminId' => 'admin.id',
                        'supportTicketStudentId' => 'student.id',
                        'supportTicketStudentSchoolName' => 'school._name',
                        'supportTicketStudentSchoolId' => 'school.id',
                        'supportTicketCategoryId' => 'ticketCategory.id',
                        'supportTicketCategoryName' => 'ticketCategoryName',
                        'supportTicketPriorityId' => 'ticketCategory._ticket_priority_id',
                        'supportTicketTotalReplies' => '(select count(id) from tickets where _is_response = 1 and _ticket_id = ticket.id)',
                        'supportTicketLastReplyDate' => '(select _date from tickets where _is_response = 1 and _ticket_id = ticket.id order by id desc limit 1)'
                    ]
                ];

                $result = $fetch($localData);

                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                        $data['categoryId'] = $this->getUtils()->getHashOfData($data['categoryId']);
                        $data['priorityId'] = $this->getUtils()->getHashOfData($data['priorityId']);

                        $senderId = $this->getUtils()::getDataFromArray($data,'senderId');
                        $senderType = $this->getUtils()::getDataFromArray($data,'sender');

                        $isUser = 0;
                        $userData = [];

                        if($senderType === 'student'){
                            if($senderId == $this->getSession()->get('studentData-->id')){
                                $isUser = 1;
                            }

                            $userData = [
                                'name' => $this->getUtils()::getDataFromArray($data,'studentUserName'),
                                'id' => $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($data,'studentUserId')),
                                'data' => $this->getUtils()::getDataFromArray($data,'studentUserData')
                            ];
                        }else{
                            if($senderId == $this->getSession()->get("adminData-->{$senderType}-->id")){
                                $isUser = 1;
                            }

                            $userData = [
                                'name' => $this->getUtils()::getDataFromArray($data,'adminUserName'),
                                'id' => $this->getUtils()->getHashOfData($this->getUtils()::getDataFromArray($data,'adminUserId')),
                                'data' => $this->getUtils()::getDataFromArray($data,'adminUserData')
                            ];
                        }


                        if(is_array($userData)){
                            $this->getUtils()::removeDataFromArray($data,'adminUserId');
                            $this->getUtils()::removeDataFromArray($data,'adminUserName');
                            $this->getUtils()::removeDataFromArray($data,'adminUserData');
                            $this->getUtils()::removeDataFromArray($data,'studentUserId');
                            $this->getUtils()::removeDataFromArray($data,'studentUserName');
                            $this->getUtils()::removeDataFromArray($data,'studentUserData');

                            $userData['data'] = json_decode($userData['data'],true);
                            if(is_array($userData['data'])){
                                $coverImageUrl = $this->getUtils()::getDataFromArray($userData['data'],'coverImage');

                                if($coverImageUrl){
                                    $coverImageList = explode('/assets/',$coverImageUrl);
                                    $coverImageUrl = "/assets/{$coverImageList[1]}";
                                }else{
                                    $coverImageUrl = null;
                                }
                                $userData['data']['coverImage'] = $coverImageUrl;

                                $stateOfResidenceId = $this->getUtils()::getDataFromArray($userData['data'],"stateOfResidence-->id");

                                if(is_numeric($stateOfResidenceId)){
                                    $stateData = $db->query("select _name as name,id as id from regions where id = {$stateOfResidenceId} limit 1")->result();

                                    $this->getUtils()::removeDataFromArray($userData['data'],"stateOfResidence");
                                    if($stateData){
                                        $stateName = $stateData[0]['name'];
                                        $stateId = $this->getUtils()->getHashOfData($stateData[0]['id']);

                                        $this->getUtils()::setDataInArray($userData['data'],"stateOfResidence-->id",$stateId);
                                        $this->getUtils()::setDataInArray($userData,"stateOfResidence-->name",$stateName);
                                    }
                                }
                            }

                            $this->getUtils()::setDataInArray($data,'userData',$userData);
                        }

                        if($isUser){
                            $this->getUtils()::setDataInArray($data,'canEdit',1);
                            $this->getUtils()::setDataInArray($data,'canDelete',1);
                        }
                    }
                    return $result;
                }

                return [];
            break;

            case 'general-administrators-count':
                $localData = [
                    'query' => 'select count(admin.id) as total from administrators as admin left join users as adminPortfolio on (admin._user_id = adminPortfolio.id) left join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) left join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) left join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id)',
                    'glue' => '(admin._type = \'general-administrator\')',
                    'checkList' => [
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminRegistrationDate' => 'adminPortfolio._date',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'general-administrators-list':
                $localData = [
                    'query' => 'select adminPortfolio.id as adminUserId,admin.id as adminId,adminPortfolio._name as adminName,adminPortfolio._email as adminEmail,adminPortfolio._phone_number as adminPhoneNumber,adminPortfolio._region_id as adminRegionId,adminRegion._name as adminRegionName,adminPortfolio._country_id as adminCountryId,adminCountry._name as adminCountryName,adminPortfolio._lga_id as adminLgaId,adminLga._name as adminLgaName,admin._is_approved as isApproved,admin._is_blocked as isBlocked,admin._approval_request_date as approvalRequestDate,admin._approved_date as approvedDate,adminPortfolio._date as registeredDate from administrators as admin left join users as adminPortfolio on (admin._user_id = adminPortfolio.id) left join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) left join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) left join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id)',
                    'glue' => '(admin._type = \'general-administrator\')',
                    'checkList' => [
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminRegistrationDate' => 'adminPortfolio._date',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['adminUserId'] = $this->getUtils()->getHashOfData($data['adminUserId']);
                        $data['adminId'] = $this->getUtils()->getHashOfData($data['adminId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'support-administrators-count':
                $localData = [
                    'query' => 'select count(admin.id) as total from administrators as admin left join users as adminPortfolio on (admin._user_id = adminPortfolio.id) left join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) left join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) left join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id)',
                    'glue' => '(admin._type = \'support-administrator\')',
                    'checkList' => [
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'support-administrators-list':
                $localData = [
                    'query' => 'select adminPortfolio.id as adminUserId,admin.id as adminId,adminPortfolio._name as adminName,adminPortfolio._email as adminEmail,adminPortfolio._phone_number as adminPhoneNumber,adminPortfolio._region_id as adminRegionId,adminRegion._name as adminRegionName,adminPortfolio._country_id as adminCountryId,adminCountry._name as adminCountryName,adminPortfolio._lga_id as adminLgaId,adminLga._name as adminLgaName,admin._is_approved as isApproved,admin._is_blocked as isBlocked,admin._approval_request_date as approvalRequestDate,admin._approved_date as approvedDate from administrators as admin left join users as adminPortfolio on (admin._user_id = adminPortfolio.id) left join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) left join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) left join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id)',
                    'glue' => '(admin._type = \'support-administrator\')',
                    'checkList' => [
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['adminUserId'] = $this->getUtils()->getHashOfData($data['adminUserId']);
                        $data['adminId'] = $this->getUtils()->getHashOfData($data['adminId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'secondary-school-administrators-count':
                $localData = [
                    'query' => 'select count(admin.id) as total from administrators as admin join secondary_schools as stakeholder on (admin._stakeholder_id = stakeholder.id) join users as adminPortfolio on (admin._user_id = adminPortfolio.id) join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id) join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'glue' => '(admin._type = \'secondary-school-administrator\')',
                    'checkList' => [
                        'secondarySchoolId' => 'stakeholder.id',
                        'secondarySchoolName' => 'stakeholder._name',
                        'secondarySchoolUniqueName' => 'stakeholder._unique_name',
                        'secondarySchoolCountryName' => 'stakeholderCountry._name',
                        'secondarySchoolRegionName' => 'stakeholderRegion._name',
                        'secondarySchoolLgaName' => 'stakeholderLga._name',
                        'secondarySchoolCountryId' => 'stakeholderCountry.id',
                        'secondarySchoolRegionId' => 'stakeholderRegion.id',
                        'secondarySchoolLgaId' => 'stakeholderLga.id',
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'secondary-school-administrators-list':
                $localData = [
                    'query' => 'select adminPortfolio.id as adminUserId,admin.id as adminId,stakeholder.id as stakeholderId,adminPortfolio._name as adminName,adminPortfolio._email as adminEmail,adminPortfolio._phone_number as adminPhoneNumber,adminPortfolio._region_id as adminRegionId,adminRegion._name as adminRegionName,adminPortfolio._country_id as adminCountryId,adminCountry._name as adminCountryName,adminPortfolio._lga_id as adminLgaId,adminLga._name as adminLgaName,admin._is_approved as isApproved,admin._is_blocked as isBlocked,admin._approval_request_date as approvalRequestDate,admin._approved_date as approvedDate,stakeholder._name as stakeholderName,stakeholder._unique_name as stakeholderUniqueName,stakeholder._region_id as stakeholderRegionId,stakeholderRegion._name as stakeholderRegionName,stakeholder._country_id as stakeholderCountryId,stakeholderCountry._name as stakeholderCountryName,stakeholder._lga_id as stakeholderLgaId,stakeholderLga._name as stakeholderLgaName,stakeholder._address as stakeholderAddress,stakeholder._description as stakeholderDescription from administrators as admin join secondary_schools as stakeholder on (admin._stakeholder_id = stakeholder.id) join users as adminPortfolio on (admin._user_id = adminPortfolio.id) join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id) join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'glue' => '(admin._type = \'secondary-school-administrator\')',
                    'checkList' => [
                        'secondarySchoolId' => 'stakeholder.id',
                        'secondarySchoolName' => 'stakeholder._name',
                        'secondarySchoolUniqueName' => 'stakeholder._unique_name',
                        'secondarySchoolCountryName' => 'stakeholderCountry._name',
                        'secondarySchoolRegionName' => 'stakeholderRegion._name',
                        'secondarySchoolLgaName' => 'stakeholderLga._name',
                        'secondarySchoolCountryId' => 'stakeholderCountry.id',
                        'secondarySchoolRegionId' => 'stakeholderRegion.id',
                        'secondarySchoolLgaId' => 'stakeholderLga.id',
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);

                if($result){
                    foreach($result as &$data){
                        $data['adminUserId'] = $this->getUtils()->getHashOfData($data['adminUserId']);
                        $data['adminId'] = $this->getUtils()->getHashOfData($data['adminId']);
                        $data['stakeholderId'] = $this->getUtils()->getHashOfData($data['stakeholderId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'institution-administrators-count':
                $localData = [
                    'query' => 'select count(admin.id) as total from administrators as admin join institutions as stakeholder on (admin._stakeholder_id = stakeholder.id) join users as adminPortfolio on (admin._user_id = adminPortfolio.id) join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id) join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'glue' => '(admin._type = \'institution-administrator\')',
                    'checkList' => [
                        'institutionId' => 'stakeholder.id',
                        'institutionName' => 'stakeholder._name',
                        'institutionUniqueName' => 'stakeholder._unique_name',
                        'institutionCountryName' => 'stakeholderCountry._name',
                        'institutionRegionName' => 'stakeholderRegion._name',
                        'institutionLgaName' => 'stakeholderLga._name',
                        'institutionCountryId' => 'stakeholderCountry.id',
                        'institutionRegionId' => 'stakeholderRegion.id',
                        'institutionLgaId' => 'stakeholderLga.id',
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'institution-administrators-list':
                $localData = [
                    'query' => 'select adminPortfolio.id as adminUserId,admin.id as adminId,stakeholder.id as stakeholderId,adminPortfolio._name as adminName,adminPortfolio._email as adminEmail,adminPortfolio._phone_number as adminPhoneNumber,adminPortfolio._region_id as adminRegionId,adminRegion._name as adminRegionName,adminPortfolio._country_id as adminCountryId,adminCountry._name as adminCountryName,adminPortfolio._lga_id as adminLgaId,adminLga._name as adminLgaName,admin._is_approved as isApproved,admin._is_blocked as isBlocked,admin._approval_request_date as approvalRequestDate,admin._approved_date as approvedDate,stakeholder._name as stakeholderName,stakeholder._unique_name as stakeholderUniqueName,stakeholder._region_id as stakeholderRegionId,stakeholderRegion._name as stakeholderRegionName,stakeholder._country_id as stakeholderCountryId,stakeholderCountry._name as stakeholderCountryName,stakeholder._lga_id as stakeholderLgaId,stakeholderLga._name as stakeholderLgaName,stakeholder._address as stakeholderAddress,stakeholder._description as stakeholderDescription from administrators as admin join institutions as stakeholder on (admin._stakeholder_id = stakeholder.id) join users as adminPortfolio on (admin._user_id = adminPortfolio.id) join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id) join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'glue' => '(admin._type = \'institution-administrator\')',
                    'checkList' => [
                        'institutionId' => 'stakeholder.id',
                        'institutionName' => 'stakeholder._name',
                        'institutionUniqueName' => 'stakeholder._unique_name',
                        'institutionCountryName' => 'stakeholderCountry._name',
                        'institutionRegionName' => 'stakeholderRegion._name',
                        'institutionLgaName' => 'stakeholderLga._name',
                        'institutionCountryId' => 'stakeholderCountry.id',
                        'institutionRegionId' => 'stakeholderRegion.id',
                        'institutionLgaId' => 'stakeholderLga.id',
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['adminUserId'] = $this->getUtils()->getHashOfData($data['adminUserId']);
                        $data['adminId'] = $this->getUtils()->getHashOfData($data['adminId']);
                        $data['stakeholderId'] = $this->getUtils()->getHashOfData($data['stakeholderId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'internship-provider-administrators-count':
                $localData = [
                    'query' => 'select count(admin.id) as total from administrators as admin join internship_providers as stakeholder on (admin._stakeholder_id = stakeholder.id) join users as adminPortfolio on (admin._user_id = adminPortfolio.id) join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id) join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'glue' => '(admin._type = \'internship-provider-administrator\')',
                    'checkList' => [
                        'internshipProviderId' => 'stakeholder.id',
                        'internshipProviderName' => 'stakeholder._name',
                        'internshipProviderUniqueName' => 'stakeholder._unique_name',
                        'internshipProviderCountryName' => 'stakeholderCountry._name',
                        'internshipProviderRegionName' => 'stakeholderRegion._name',
                        'internshipProviderLgaName' => 'stakeholderLga._name',
                        'internshipProviderCountryId' => 'stakeholderCountry.id',
                        'internshipProviderRegionId' => 'stakeholderRegion.id',
                        'internshipProviderLgaId' => 'stakeholderLga.id',
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'internship-provider-administrators-list':
                $localData = [
                    'query' => 'select adminPortfolio.id as adminUserId,admin.id as adminId,stakeholder.id as stakeholderId,adminPortfolio._name as adminName,adminPortfolio._email as adminEmail,adminPortfolio._phone_number as adminPhoneNumber,adminPortfolio._region_id as adminRegionId,adminRegion._name as adminRegionName,adminPortfolio._country_id as adminCountryId,adminCountry._name as adminCountryName,adminPortfolio._lga_id as adminLgaId,adminLga._name as adminLgaName,admin._is_approved as isApproved,admin._is_blocked as isBlocked,admin._approval_request_date as approvalRequestDate,admin._approved_date as approvedDate,stakeholder._name as stakeholderName,stakeholder._unique_name as stakeholderUniqueName,stakeholder._region_id as stakeholderRegionId,stakeholderRegion._name as stakeholderRegionName,stakeholder._country_id as stakeholderCountryId,stakeholderCountry._name as stakeholderCountryName,stakeholder._lga_id as stakeholderLgaId,stakeholderLga._name as stakeholderLgaName,stakeholder._address as stakeholderAddress,stakeholder._description as stakeholderDescription from administrators as admin join internship_providers as stakeholder on (admin._stakeholder_id = stakeholder.id) join users as adminPortfolio on (admin._user_id = adminPortfolio.id) join countries as adminCountry on (adminPortfolio._country_id = adminCountry.id) join regions as adminRegion on (adminPortfolio._region_id = adminRegion.id) join lgas as adminLga on (adminPortfolio._lga_id = adminLga.id) join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'glue' => '(admin._type = \'internship-provider-administrator\')',
                    'checkList' => [
                        'internshipProviderId' => 'stakeholder.id',
                        'internshipProviderName' => 'stakeholder._name',
                        'internshipProviderUniqueName' => 'stakeholder._unique_name',
                        'internshipProviderCountryName' => 'stakeholderCountry._name',
                        'internshipProviderRegionName' => 'stakeholderRegion._name',
                        'internshipProviderLgaName' => 'stakeholderLga._name',
                        'internshipProviderCountryId' => 'stakeholderCountry.id',
                        'internshipProviderRegionId' => 'stakeholderRegion.id',
                        'internshipProviderLgaId' => 'stakeholderLga.id',
                        'adminName' => 'adminPortfolio._name',
                        'adminEmail' => 'adminPortfolio._email',
                        'adminPhone' => 'adminPortfolio._phone_number',
                        'adminCountryName' => 'adminCountry._name',
                        'adminRegionName' => 'adminRegion._name',
                        'adminLgaName' => 'adminLga._name',
                        'adminCountryId' => 'adminCountry.id',
                        'adminRegionId' => 'adminRegion.id',
                        'adminLgaId' => 'adminLga.id',
                        'adminApprovalStatus' => 'admin._is_approved',
                        'adminBlockedStatus' => 'admin._is_blocked',
                        'adminApprovalDate' => 'admin._approved_date',
                        'adminApprovalRequestDate' => 'admin._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['adminUserId'] = $this->getUtils()->getHashOfData($data['adminUserId']);
                        $data['adminId'] = $this->getUtils()->getHashOfData($data['adminId']);
                        $data['stakeholderId'] = $this->getUtils()->getHashOfData($data['stakeholderId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'institution-id-from-name':
                $name = $this->getUtils()::getDataFromArray($data,'name');
                $result = $db->prepare('select institution.id as id from institutions as institution where institution._name = :name limit 1')->bind([
                    'name' => &$name
                ])->result();

                if($result){
                    return (int) $result[0]['id'];
                }

                return 0;
            break;

            case 'institution-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from institutions as institution where institution.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();

                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'institutions-count':
                $localData = [
                    'query' => 'select count(stakeholder.id) as total from institutions as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'institutionId' => 'stakeholder.id',
                        'institutionName' => 'stakeholder._name',
                        'institutionUniqueName' => 'stakeholder._unique_name',
                        'institutionCountryName' => 'stakeholderCountry._name',
                        'institutionRegionName' => 'stakeholderRegion._name',
                        'institutionLgaName' => 'stakeholderLga._name',
                        'institutionCountryId' => 'stakeholderCountry.id',
                        'institutionRegionId' => 'stakeholderRegion.id',
                        'institutionLgaId' => 'stakeholderLga.id',
                        'institutionApprovalStatus' => 'stakeholder._is_approved',
                        'institutionBlockedStatus' => 'stakeholder._is_blocked',
                        'institutionApprovalDate' => 'stakeholder._approved_date',
                        'institutionApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'institutions-list':
                $localData = [
                    'query' => 'select stakeholder.id as stakeholderId,stakeholder._name as stakeholderName,stakeholder._unique_name as stakeholderUniqueName,stakeholder._region_id as stakeholderRegionId,stakeholderRegion._name as stakeholderRegionName,stakeholder._country_id as stakeholderCountryId,stakeholderCountry._name as stakeholderCountryName,stakeholder._lga_id as stakeholderLgaId,stakeholderLga._name as stakeholderLgaName,stakeholder._is_approved as isApproved,stakeholder._is_blocked as isBlocked,stakeholder._approval_request_date as approvalRequestDate,stakeholder._approved_date as approvedDate,stakeholder._address as stakeholderAddress,stakeholder._description as stakeholderDescription from institutions as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'institutionId' => 'stakeholder.id',
                        'institutionName' => 'stakeholder._name',
                        'institutionUniqueName' => 'stakeholder._unique_name',
                        'institutionCountryName' => 'stakeholderCountry._name',
                        'institutionRegionName' => 'stakeholderRegion._name',
                        'institutionLgaName' => 'stakeholderLga._name',
                        'institutionCountryId' => 'stakeholderCountry.id',
                        'institutionRegionId' => 'stakeholderRegion.id',
                        'institutionLgaId' => 'stakeholderLga.id',
                        'institutionApprovalStatus' => 'stakeholder._is_approved',
                        'institutionBlockedStatus' => 'stakeholder._is_blocked',
                        'institutionApprovalDate' => 'stakeholder._approved_date',
                        'institutionApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['stakeholderId'] = $this->getUtils()->getHashOfData($data['stakeholderId']);
                        $data['stakeholderCountryId'] = $this->getUtils()->getHashOfData($data['stakeholderCountryId']);
                        $data['stakeholderRegionId'] = $this->getUtils()->getHashOfData($data['stakeholderRegionId']);
                        $data['stakeholderLgaId'] = $this->getUtils()->getHashOfData($data['stakeholderLgaId']);
                    }
                    return $result;
                }
                return [];
            break;

            case 'institution-data':
                $institutionId = $this->getUtils()::getDataFromArray($data,'institutionId');
                $query = 'select stakeholder.id as id,stakeholder._name as name,stakeholder._unique_name as uniqueName,stakeholder._region_id as regionId,stakeholderRegion._name as regionName,stakeholder._country_id as countryId,stakeholderCountry._name as countryName,stakeholder._lga_id as lgaId,stakeholderLga._name as lgaName,stakeholder._address as address,stakeholder._description as description,stakeholder._data as data from institutions as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id) where stakeholder.id = :institutionId limit 1';
                $data = [
                    'institutionId' => &$institutionId
                ];

                $result = $db->prepare($query)->bind($data)->result(false);
                if($result){
                    $result[0]['id'] = $this->getUtils()->getHashOfData($result[0]['id']);
                    $result[0]['regionId'] = $this->getUtils()->getHashOfData($result[0]['regionId']);
                    $result[0]['countryId'] = $this->getUtils()->getHashOfData($result[0]['countryId']);
                    $result[0]['lgaId'] = $this->getUtils()->getHashOfData($result[0]['lgaId']);
                    $result[0]['data'] = \json_decode($result[0]['data'],true);

                    if(is_array($result[0]['data'])){
                        $coverImageUrl = $this->getUtils()::getDataFromArray($result[0]['data'],'coverImage');
                        if($coverImageUrl){
                            $coverImageList = explode('/assets/',$coverImageUrl);
                            $coverImageUrl = "/assets/{$coverImageList[1]}";
                        }else{
                            $coverImageUrl = null;
                        }
                        $result[0]['coverImage'] = $coverImageUrl;
                    }
                    return $result[0];
                }

                return [];
            break;

            case 'internship-providers-count':
                $localData = [
                    'query' => 'select count(stakeholder.id) as total from internship_providers as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'internshipProviderId' => 'stakeholder.id',
                        'internshipProviderName' => 'stakeholder._name',
                        'internshipProviderUniqueName' => 'stakeholder._unique_name',
                        'internshipProviderCountryName' => 'stakeholderCountry._name',
                        'internshipProviderRegionName' => 'stakeholderRegion._name',
                        'internshipProviderLgaName' => 'stakeholderLga._name',
                        'internshipProviderCountryId' => 'stakeholderCountry.id',
                        'internshipProviderRegionId' => 'stakeholderRegion.id',
                        'internshipProviderLgaId' => 'stakeholderLga.id',
                        'internshipProviderApprovalStatus' => 'stakeholder._is_approved',
                        'internshipProviderBlockedStatus' => 'stakeholder._is_blocked',
                        'internshipProviderApprovalDate' => 'stakeholder._approved_date',
                        'internshipProviderApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'internship-providers-list':
                $localData = [
                    'query' => 'select stakeholder.id as stakeholderId,stakeholder._name as stakeholderName,stakeholder._unique_name as stakeholderUniqueName,stakeholder._region_id as stakeholderRegionId,stakeholderRegion._name as stakeholderRegionName,stakeholder._country_id as stakeholderCountryId,stakeholderCountry._name as stakeholderCountryName,stakeholder._lga_id as stakeholderLgaId,stakeholderLga._name as stakeholderLgaName, stakeholder._is_approved as isApproved,stakeholder._is_blocked as isBlocked,stakeholder._approval_request_date as approvalRequestDate,stakeholder._approved_date as approvedDate,stakeholder._address as stakeholderAddress,stakeholder._description as stakeholderDescription from internship_providers as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'internshipProviderId' => 'stakeholder.id',
                        'internshipProviderName' => 'stakeholder._name',
                        'internshipProviderUniqueName' => 'stakeholder._unique_name',
                        'internshipProviderCountryName' => 'stakeholderCountry._name',
                        'internshipProviderRegionName' => 'stakeholderRegion._name',
                        'internshipProviderLgaName' => 'stakeholderLga._name',
                        'internshipProviderCountryId' => 'stakeholderCountry.id',
                        'internshipProviderRegionId' => 'stakeholderRegion.id',
                        'internshipProviderLgaId' => 'stakeholderLga.id',
                        'internshipProviderApprovalStatus' => 'stakeholder._is_approved',
                        'internshipProviderBlockedStatus' => 'stakeholder._is_blocked',
                        'internshipProviderApprovalDate' => 'stakeholder._approved_date',
                        'internshipProviderApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['stakeholderId'] = $this->getUtils()->getHashOfData($data['stakeholderId']);
                        $data['stakeholderCountryId'] = $this->getUtils()->getHashOfData($data['stakeholderCountryId']);
                        $data['stakeholderRegionId'] = $this->getUtils()->getHashOfData($data['stakeholderRegionId']);
                        $data['stakeholderLgaId'] = $this->getUtils()->getHashOfData($data['stakeholderLgaId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'secondary-schools-data-count':
                $localData = [
                    'query' => 'select count(stakeholder.id) as total from secondary_schools as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'secondarySchoolId' => 'stakeholder.id',
                        'secondarySchoolName' => 'stakeholder._name',
                        'secondarySchoolUniqueName' => 'stakeholder._unique_name',
                        'secondarySchoolCountryName' => 'stakeholderCountry._name',
                        'secondarySchoolRegionName' => 'stakeholderRegion._name',
                        'secondarySchoolLgaName' => 'stakeholderLga._name',
                        'secondarySchoolCountryId' => 'stakeholderCountry.id',
                        'secondarySchoolRegionId' => 'stakeholderRegion.id',
                        'secondarySchoolLgaId' => 'stakeholderLga.id',
                        'schoolApprovalStatus' => 'stakeholder._is_approved',
                        'schoolBlockedStatus' => 'stakeholder._is_blocked',
                        'schoolApprovalDate' => 'stakeholder._approved_date',
                        'schoolApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'secondary-schools-data-list':
                $localData = [
                    'query' => 'select stakeholder.id as stakeholderId,stakeholder._name as stakeholderName,stakeholder._region_id as stakeholderRegionId,stakeholderRegion._name as stakeholderRegionName,stakeholder._country_id as stakeholderCountryId,stakeholder._unique_name as stakeholderUniqueName,stakeholderCountry._name as stakeholderCountryName,stakeholder._lga_id as stakeholderLgaId,stakeholderLga._name as stakeholderLgaName,stakeholder._address as stakeholderAddress,stakeholder._description as stakeholderDescription,(select count(id) from students where _secondary_school_id = stakeholder.id and _is_approved = 1) as totalApprovedStudents,(select count(id) from students where _secondary_school_id = stakeholder.id and _is_approved = 0 and isnull(_approved_date)) as totalPendingStudents,(select count(id) from students where _secondary_school_id = stakeholder.id and _is_approved = 0 and (not isnull(_approved_date))) as totalRejectedStudents,(select count(id) from administrators where _type = \'secondary-school-administrator\' and _stakeholder_id = stakeholder.id and _is_approved = 1) as totalApprovedAdministrators,stakeholder._is_approved as isApproved,stakeholder._is_blocked as isBlocked,stakeholder._approval_request_date as approvalRequestDate,stakeholder._approved_date as approvedDate from secondary_schools as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'secondarySchoolId' => 'stakeholder.id',
                        'secondarySchoolName' => 'stakeholder._name',
                        'secondarySchoolUniqueName' => 'stakeholder._unique_name',
                        'secondarySchoolCountryName' => 'stakeholderCountry._name',
                        'secondarySchoolRegionName' => 'stakeholderRegion._name',
                        'secondarySchoolLgaName' => 'stakeholderLga._name',
                        'secondarySchoolCountryId' => 'stakeholderCountry.id',
                        'secondarySchoolRegionId' => 'stakeholderRegion.id',
                        'secondarySchoolLgaId' => 'stakeholderLga.id',
                        'schoolApprovalStatus' => 'stakeholder._is_approved',
                        'schoolBlockedStatus' => 'stakeholder._is_blocked',
                        'schoolApprovalDate' => 'stakeholder._approved_date',
                        'schoolApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['stakeholderId'] = $this->getUtils()->getHashOfData($data['stakeholderId']);
                        $data['stakeholderCountryId'] = $this->getUtils()->getHashOfData($data['stakeholderCountryId']);
                        $data['stakeholderRegionId'] = $this->getUtils()->getHashOfData($data['stakeholderRegionId']);
                        $data['stakeholderLgaId'] = $this->getUtils()->getHashOfData($data['stakeholderLgaId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'secondary-schools-count':
                $localData = [
                    'query' => 'select count(stakeholder.id) as total from secondary_schools as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'secondarySchoolId' => 'stakeholder.id',
                        'secondarySchoolName' => 'stakeholder._name',
                        'secondarySchoolUniqueName' => 'stakeholder._unique_name',
                        'secondarySchoolCountryName' => 'stakeholderCountry._name',
                        'secondarySchoolRegionName' => 'stakeholderRegion._name',
                        'secondarySchoolLgaName' => 'stakeholderLga._name',
                        'secondarySchoolCountryId' => 'stakeholderCountry.id',
                        'secondarySchoolRegionId' => 'stakeholderRegion.id',
                        'secondarySchoolLgaId' => 'stakeholderLga.id',
                        'schoolApprovalStatus' => 'stakeholder._is_approved',
                        'schoolBlockedStatus' => 'stakeholder._is_blocked',
                        'schoolApprovalDate' => 'stakeholder._approved_date',
                        'schoolApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'secondary-schools-list':
                $localData = [
                    'query' => 'select stakeholder.id as stakeholderId,stakeholder._name as stakeholderName,stakeholder._region_id as stakeholderRegionId,stakeholderRegion._name as stakeholderRegionName,stakeholder._country_id as stakeholderCountryId,stakeholder._unique_name as stakeholderUniqueName,stakeholderCountry._name as stakeholderCountryName,stakeholder._lga_id as stakeholderLgaId,stakeholderLga._name as stakeholderLgaName,stakeholder._address as stakeholderAddress,stakeholder._description as stakeholderDescription,stakeholder._is_approved as isApproved,stakeholder._is_blocked as isBlocked,stakeholder._approval_request_date as approvalRequestDate,stakeholder._approved_date as approvedDate from secondary_schools as stakeholder join countries as stakeholderCountry on (stakeholder._country_id = stakeholderCountry.id) join regions as stakeholderRegion on (stakeholder._region_id = stakeholderRegion.id) join lgas as stakeholderLga on (stakeholder._lga_id = stakeholderLga.id)',
                    'checkList' => [
                        'secondarySchoolId' => 'stakeholder.id',
                        'secondarySchoolName' => 'stakeholder._name',
                        'secondarySchoolUniqueName' => 'stakeholder._unique_name',
                        'secondarySchoolCountryName' => 'stakeholderCountry._name',
                        'secondarySchoolRegionName' => 'stakeholderRegion._name',
                        'secondarySchoolLgaName' => 'stakeholderLga._name',
                        'secondarySchoolCountryId' => 'stakeholderCountry.id',
                        'secondarySchoolRegionId' => 'stakeholderRegion.id',
                        'secondarySchoolLgaId' => 'stakeholderLga.id',
                        'schoolApprovalStatus' => 'stakeholder._is_approved',
                        'schoolBlockedStatus' => 'stakeholder._is_blocked',
                        'schoolApprovalDate' => 'stakeholder._approved_date',
                        'schoolApprovalRequestDate' => 'stakeholder._approval_request_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['stakeholderId'] = $this->getUtils()->getHashOfData($data['stakeholderId']);
                        $data['stakeholderCountryId'] = $this->getUtils()->getHashOfData($data['stakeholderCountryId']);
                        $data['stakeholderRegionId'] = $this->getUtils()->getHashOfData($data['stakeholderRegionId']);
                        $data['stakeholderLgaId'] = $this->getUtils()->getHashOfData($data['stakeholderLgaId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'students-count':
                $localData = [
                    'query' => 'select count(student.id) as total from students as student join secondary_schools as school on (student._secondary_school_id = school.id) join users as studentPortfolio on (student._user_id = studentPortfolio.id) join countries as studentCountry on (studentPortfolio._country_id = studentCountry.id) join regions as studentRegion on (studentPortfolio._region_id = studentRegion.id) join lgas as studentLga on (studentPortfolio._lga_id = studentLga.id) join countries as schoolCountry on (school._country_id = schoolCountry.id) join regions as schoolRegion on (school._region_id = schoolRegion.id) join lgas as schoolLga on (school._lga_id = schoolLga.id)',
                    'checkList' => [
                        'studentId' => 'student.id',
                        'userId' => 'student._user_id',
                        'schoolId' => 'school.id',
                        'schoolName' => 'school._name',
                        'schoolUniqueName' => 'stakeholder._unique_name',
                        'schoolCountryName' => 'schoolCountry._name',
                        'schoolRegionName' => 'schoolRegion._name',
                        'schoolLgaName' => 'schoolLga._name',
                        'schoolCountryId' => 'schoolCountry.id',
                        'schoolRegionId' => 'schoolRegion.id',
                        'schoolLgaId' => 'schoolLga.id',
                        'hasInternship' => 'student._has_internship',
                        'studentInternshipApprovalStatus' => 'studentIntern._is_approved',
                        'studentName' => 'studentPortfolio._name',
                        'studentUniqueName' => 'studentPortfolio._unique_name',
                        'studentCountryName' => 'studentCountry._name',
                        'studentRegionName' => 'studentRegion._name',
                        'studentLgaName' => 'studentLga._name',
                        'studentCountryId' => 'studentCountry.id',
                        'studentRegionId' => 'studentRegion.id',
                        'studentLgaId' => 'studentLga.id',
                        'studentLevel' => 'student._level',
                        'studentApprovalStatus' => 'student._is_approved',
                        'studentBlockedStatus' => 'student._is_blocked',
                        'studentApprovalDate' => 'student._approved_date',
                        'studentApprovalRequestDate' => 'student._approval_request_date',
                        'studentRegisterDate' => 'studentPortfolio._date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'students-list':
                $localData = [
                    'query' => 'select studentPortfolio.id as studentUserId,student.id as studentId,school.id as schoolId,studentPortfolio._name as studentName,studentPortfolio._unique_name as studentUniqueName,studentPortfolio._region_id as studentRegionId,studentRegion._name as studentRegionName,studentPortfolio._country_id as studentCountryId,studentCountry._name as studentCountryName,studentPortfolio._lga_id as studentLgaId,studentLga._name as studentLgaName,studentPortfolio._date as registeredDate,school._name as schoolName,school._region_id as schoolRegionId,schoolRegion._name as schoolRegionName,school._country_id as schoolCountryId,schoolCountry._name as schoolCountryName,school._lga_id as schoolLgaId,schoolLga._name as schoolLgaName,student._level as studentLevel,student._is_approved as isApproved,student._is_blocked as isBlocked,student._approval_request_date as approvalRequestDate,student._approved_date as approvedDate,school._address as schoolAddress,school._description as schoolDescription from students as student join secondary_schools as school on (student._secondary_school_id = school.id) join users as studentPortfolio on (student._user_id = studentPortfolio.id) join countries as studentCountry on (studentPortfolio._country_id = studentCountry.id) join regions as studentRegion on (studentPortfolio._region_id = studentRegion.id) join lgas as studentLga on (studentPortfolio._lga_id = studentLga.id) join countries as schoolCountry on (school._country_id = schoolCountry.id) join regions as schoolRegion on (school._region_id = schoolRegion.id) join lgas as schoolLga on (school._lga_id = schoolLga.id)',
                    'checkList' => [
                        'studentId' => 'student.id',
                        'userId' => 'student._user_id',
                        'schoolId' => 'school.id',
                        'schoolName' => 'school._name',
                        'schoolUniqueName' => 'stakeholder._unique_name',
                        'schoolCountryName' => 'schoolCountry._name',
                        'schoolRegionName' => 'schoolRegion._name',
                        'schoolLgaName' => 'schoolLga._name',
                        'schoolCountryId' => 'schoolCountry.id',
                        'schoolRegionId' => 'schoolRegion.id',
                        'schoolLgaId' => 'schoolLga.id',
                        'hasInternship' => 'student._has_internship',
                        'studentInternshipApprovalStatus' => 'studentIntern._is_approved',
                        'studentName' => 'studentPortfolio._name',
                        'studentUniqueName' => 'studentPortfolio._unique_name',
                        'studentCountryName' => 'studentCountry._name',
                        'studentRegionName' => 'studentRegion._name',
                        'studentLgaName' => 'studentLga._name',
                        'studentCountryId' => 'studentCountry.id',
                        'studentRegionId' => 'studentRegion.id',
                        'studentLgaId' => 'studentLga.id',
                        'studentLevel' => 'student._level',
                        'studentApprovalStatus' => 'student._is_approved',
                        'studentBlockedStatus' => 'student._is_blocked',
                        'studentApprovalDate' => 'student._approved_date',
                        'studentApprovalRequestDate' => 'student._approval_request_date',
                        'studentRegisterDate' => 'studentPortfolio._date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['studentUserId'] = $this->getUtils()->getHashOfData($data['studentUserId']);
                        $data['studentId'] = $this->getUtils()->getHashOfData($data['studentId']);
                        $data['schoolId'] = $this->getUtils()->getHashOfData($data['schoolId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'student-internships-count':
                $localData = [
                    'query' => 'select count(student.id) as total from students as student join secondary_schools as school on (student._secondary_school_id = school.id) join users as studentPortfolio on (student._user_id = studentPortfolio.id) join internships as studentInternship on (student.id = studentInternship._student_id) join internship_providers as internshipProvider on (studentInternship._internship_provider_id = internshipProvider.id) join countries as studentCountry on (studentPortfolio._country_id = studentCountry.id) join regions as studentRegion on (studentPortfolio._region_id = studentRegion.id) join lgas as studentLga on (studentPortfolio._lga_id = studentLga.id) join countries as schoolCountry on (school._country_id = schoolCountry.id) join regions as schoolRegion on (school._region_id = schoolRegion.id) join lgas as schoolLga on (school._lga_id = schoolLga.id) join countries as internshipProviderCountry on (internshipProvider._country_id = internshipProviderCountry.id) join regions as internshipProviderRegion on (internshipProvider._region_id = internshipProviderRegion.id) join lgas as internshipProviderLga on (internshipProvider._lga_id = internshipProviderLga.id)',
                    'checkList' => [
                        'studentId' => 'student.id',
                        'userId' => 'student._user_id',
                        'schoolId' => 'school.id',
                        'schoolName' => 'school._name',
                        'schoolUniqueName' => 'stakeholder._unique_name',
                        'schoolCountryName' => 'schoolCountry._name',
                        'schoolRegionName' => 'schoolRegion._name',
                        'schoolLgaName' => 'schoolLga._name',
                        'schoolCountryId' => 'schoolCountry.id',
                        'schoolRegionId' => 'schoolRegion.id',
                        'schoolLgaId' => 'schoolLga.id',
                        'internshipProviderName' => 'internshipProvider._name',
                        'internshipProviderCountryName' => 'internshipProviderCountry._name',
                        'internshipProviderRegionName' => 'internshipProviderRegion._name',
                        'internshipProviderLgaName' => 'internshipProviderLga._name',
                        'internshipProviderCountryId' => 'internshipProviderCountry.id',
                        'internshipProviderRegionId' => 'internshipProviderRegion.id',
                        'schoolLgaId' => 'schoolLga.id',
                        'hasInternship' => 'student._has_internship',
                        'studentInternshipApprovalStatus' => 'studentIntern._is_approved',
                        'studentName' => 'studentPortfolio._name',
                        'studentUniqueName' => 'studentPortfolio._unique_name',
                        'studentCountryName' => 'studentCountry._name',
                        'studentRegionName' => 'studentRegion._name',
                        'studentLgaName' => 'studentLga._name',
                        'studentCountryId' => 'studentCountry.id',
                        'studentRegionId' => 'studentRegion.id',
                        'studentLgaId' => 'studentLga.id',
                        'studentLevel' => 'student._level',
                        'studentApprovalStatus' => 'student._is_approved',
                        'studentBlockedStatus' => 'student._is_blocked',
                        'studentApprovalDate' => 'student._approved_date',
                        'studentApprovalRequestDate' => 'student._approval_request_date',
                        'studentRegisterDate' => 'studentPortfolio._date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'student-internships-list':
                $localData = [
                    'query' => 'select studentPortfolio.id as studentUserId,student.id as studentId,school.id as schoolId,studentPortfolio._name as studentName,studentPortfolio._unique_name as studentUniqueName,studentPortfolio._region_id as studentRegionId,studentRegion._name as studentRegionName,studentPortfolio._country_id as studentCountryId,studentCountry._name as studentCountryName,studentPortfolio._lga_id as studentLgaId,studentLga._name as studentLgaName,studentPortfolio._date as registeredDate,school._name as schoolName,school._region_id as schoolRegionId,schoolRegion._name as schoolRegionName,school._country_id as schoolCountryId,schoolCountry._name as schoolCountryName,school._lga_id as schoolLgaId,schoolLga._name as schoolLgaName,student._level as studentLevel,student._is_approved as isApproved,student._is_blocked as isBlocked,student._approval_request_date as approvalRequestDate,student._approved_date as approvedDate,school._address as schoolAddress,school._description as schoolDescription from students as student join secondary_schools as school on (student._secondary_school_id = school.id) join users as studentPortfolio on (student._user_id = studentPortfolio.id) join internships as studentInternship on (student.id = studentInternship._student_id) join internship_providers as internshipProvider on (studentInternship._internship_provider_id = internshipProvider.id) join countries as studentCountry on (studentPortfolio._country_id = studentCountry.id) join regions as studentRegion on (studentPortfolio._region_id = studentRegion.id) join lgas as studentLga on (studentPortfolio._lga_id = studentLga.id) join countries as schoolCountry on (school._country_id = schoolCountry.id) join regions as schoolRegion on (school._region_id = schoolRegion.id) join lgas as schoolLga on (school._lga_id = schoolLga.id) join countries as internshipProviderCountry on (internshipProvider._country_id = internshipProviderCountry.id) join regions as internshipProviderRegion on (internshipProvider._region_id = internshipProviderRegion.id) join lgas as internshipProviderLga on (internshipProvider._lga_id = internshipProviderLga.id)',
                    'checkList' => [
                        'studentId' => 'student.id',
                        'userId' => 'student._user_id',
                        'schoolId' => 'school.id',
                        'schoolName' => 'school._name',
                        'schoolUniqueName' => 'stakeholder._unique_name',
                        'schoolCountryName' => 'schoolCountry._name',
                        'schoolRegionName' => 'schoolRegion._name',
                        'schoolLgaName' => 'schoolLga._name',
                        'schoolCountryId' => 'schoolCountry.id',
                        'schoolRegionId' => 'schoolRegion.id',
                        'schoolLgaId' => 'schoolLga.id',
                        'internshipProviderName' => 'internshipProvider._name',
                        'internshipProviderCountryName' => 'internshipProviderCountry._name',
                        'internshipProviderRegionName' => 'internshipProviderRegion._name',
                        'internshipProviderLgaName' => 'internshipProviderLga._name',
                        'internshipProviderCountryId' => 'internshipProviderCountry.id',
                        'internshipProviderRegionId' => 'internshipProviderRegion.id',
                        'schoolLgaId' => 'schoolLga.id',
                        'hasInternship' => 'student._has_internship',
                        'studentInternshipApprovalStatus' => 'studentIntern._is_approved',
                        'studentName' => 'studentPortfolio._name',
                        'studentUniqueName' => 'studentPortfolio._unique_name',
                        'studentCountryName' => 'studentCountry._name',
                        'studentRegionName' => 'studentRegion._name',
                        'studentLgaName' => 'studentLga._name',
                        'studentCountryId' => 'studentCountry.id',
                        'studentRegionId' => 'studentRegion.id',
                        'studentLgaId' => 'studentLga.id',
                        'studentLevel' => 'student._level',
                        'studentApprovalStatus' => 'student._is_approved',
                        'studentBlockedStatus' => 'student._is_blocked',
                        'studentApprovalDate' => 'student._approved_date',
                        'studentApprovalRequestDate' => 'student._approval_request_date',
                        'studentRegisterDate' => 'studentPortfolio._date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['studentUserId'] = $this->getUtils()->getHashOfData($data['studentUserId']);
                        $data['studentId'] = $this->getUtils()->getHashOfData($data['studentId']);
                        $data['schoolId'] = $this->getUtils()->getHashOfData($data['schoolId']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'local-government-areas-count':
                $localData = [
                    'query' => 'select count(lga.id) as total from lgas as lga join regions as region on (lga._region_id = region.id) join countries as country on (country.id = region._country_id)',
                    'checkList' => [
                        'localGovernmentId' => 'lga.id',
                        'localGovernmentName' => 'lga._name',
                        'regionId' => 'region.id',
                        'regionName' => 'region._name',
                        'countryId' => 'country.id',
                        'countryName' => 'country._name'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'local-government-areas-list':
                $localData = [
                    'query' => 'select lga.id as id,lga._name as name from lgas as lga join regions as region on (lga._region_id = region.id) join countries as country on (country.id = region._country_id)',
                    'checkList' => [
                        'localGovernmentId' => 'lga.id',
                        'localGovernmentName' => 'lga._name',
                        'regionId' => 'region.id',
                        'regionName' => 'region._name',
                        'countryId' => 'country.id',
                        'countryName' => 'country._name'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'regions-count':
                $localData = [
                    'query' => 'select count(region.id) as total from regions as region join countries as country on (country.id = region._country_id)',
                    'checkList' => [
                        'regionId' => 'region.id',
                        'regionName' => 'region._name',
                        'countryId' => 'country.id',
                        'countryName' => 'country._name'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'regions-list':
                $localData = [
                    'query' => 'select region.id as id,region._name as name from regions as region join countries as country on (country.id = region._country_id)',
                    'checkList' => [
                        'regionId' => 'region.id',
                        'regionName' => 'region._name',
                        'countryId' => 'country.id',
                        'countryName' => 'country._name'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'countries-count':
                $localData = [
                    'query' => 'select count(country.id) as total from countries as country',
                    'checkList' => [
                        'countryId' => 'country.id',
                        'countryName' => 'country._name'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['total'];
                }

                return 0;
            break;

            case 'countries-list':
                $localData = [
                    'query' => 'select country.id as id,country._name as name from countries as country',
                    'checkList' => [
                        'countryId' => 'country.id',
                        'countryName' => 'country._name'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'events-count':
                $localData = [
                    'query' => 'select count(event.id) as totalEvent from events as event',
                    'checkList' => [
                        'eventId' => 'event.id',
                        'eventName' => 'event._name',
                        'eventLocation' => 'event._location',
                        'eventDate' => 'event._action_date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalEvent'];
                }

                return 0;
            break;

            case 'event-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from events as event where event.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();

                if($result){
                    return (int) $id;
                }

                return 0;
            break;

            case 'events-list':
                $localData = [
                    'query' => 'select event.id as id,event._name as name,event._location as location,event._description as description,event._action_date as actionDate,event._cover_image as coverImage,event._data as eventData from events as event',
                    'checkList' => [
                        'eventId' => 'event.id',
                        'eventName' => 'event._name',
                        'eventLocation' => 'event._location',
                        'eventDate' => 'event._action_date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                        $coverImageUrl = $data['coverImage'];
                        if($coverImageUrl){
                            $coverImageList = explode('/assets/',$coverImageUrl);
                            $data['coverImage'] = "/assets/{$coverImageList[1]}";
                        }else{
                            $data['coverImage'] = null;
                        }
                    }
                    return $result;
                }

                return [];
            break;

            case 'event-data':
                $eventId = $this->getUtils()::getDataFromArray($data,'eventId');
                $query = 'select event.id as id,event._name as name,event._added_by as addedBy,event._location as location,event._description as description,event._action_date as actionDate,event._added_date as addedDate,event._cover_image as coverImage,event._data as data from events as event where event.id = :eventId limit 1';
                $data = [
                    'eventId' => &$eventId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    $result[0]['id'] = $this->getUtils()->getHashOfData($result[0]['id']);
                    $result[0]['data'] = \json_decode($result[0]['data'],true);
                    $result[0]['actionDate'] = date('Y-m-d H:i:s',strtotime($result[0]['actionDate']));
                    $result[0]['addedDate'] = date('Y-m-d H:i:s',strtotime($result[0]['addedDate']));

                    $coverImageUrl = $result[0]['coverImage'];
                    if($coverImageUrl){
                        $coverImageList = explode('/assets/',$coverImageUrl);
                        $result[0]['coverImage'] = "/assets/{$coverImageList[1]}";
                    }else{
                        $result[0]['coverImage'] = null;
                    }

                    return $result[0];
                }
                return [];
            break;

            case 'survey-count':
                $localData = [
                    'query' => 'select count(survey.id) as totalSurvey from surveys as survey',
                    'checkList' => [
                        'surveyId' => 'survey.id',
                        'surveyName' => 'survey._name',
                        'surveyFor' => 'survey._for',
                        'surveyForId' => 'survey._for_id',
                        'isSurveyActive' => 'survey._is_active',
                        'surveyExpiryDate' => 'survey._expires_at',
                        'surveyAddedDate' => 'survey._date',
                        'totalSurveyResponses' => '(select count(id) from surveys_responses where _survey_id = survey.id)'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalEvent'];
                }

                return 0;
            break;

            case 'survey-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from surveys as survey where survey.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();
                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'survey-list':
                $localData = [
                    'query' => 'select survey.id as surveyId,survey._name as surveyName,survey._for as surveyFor,survey._for_id as surveyForId,survey._is_active as isSurveyActive,survey._expires_at as surveyExpiryDate,survey._data as surveyData,survey._date as surveyAddedDate,(select count(id) from surveys_responses where _survey_id = survey.id) as totalSurveyResponse from surveys as survey',
                    'checkList' => [
                        'surveyId' => 'survey.id',
                        'surveyName' => 'survey._name',
                        'surveyFor' => 'survey._for',
                        'surveyForId' => 'survey._for_id',
                        'isSurveyActive' => 'survey._is_active',
                        'surveyExpiryDate' => 'survey._expires_at',
                        'surveyAddedDate' => 'survey._date',
                        'totalSurveyResponses' => '(select count(id) from surveys_responses where _survey_id = survey.id)'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$survey){
                        $survey['surveyId'] = $this->getUtils()->getHashOfData($survey['surveyId']);
                        $survey['surveyData'] = \json_decode($survey['surveyData'],true);
                    }
                    return $result;
                }

                return [];
            break;

            case 'temperaments-count':
                $localData = [
                    'query' => 'select count(temperament.id) as totalTemperaments from temperaments_db as temperament',
                    'checkList' => [
                        'temperamentId' => 'temperament.id',
                        'temperamentName' => 'temperament._name',
                        'totalProfessions' => '(select count(id) from temperaments_professions where _temperament_id = temperament.id)'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalTemperaments'];
                }

                return 0;
            break;

            case 'temperament-id-from-name':
                $name = $this->getUtils()::getDataFromArray($data,'name');
                $result = $db->prepare('select temperament.id as id from temperaments_db as temperament where temperament._name = :name limit 1')->bind([
                    'name' => &$name
                ])->result();
                if($result){
                    return (int) $result[0]['id'];
                }
                return 0;
            break;

            case 'temperament-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from temperaments_db as temperament where temperament.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();
                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'temperaments-list':
                $localData = [
                    'query' => 'select temperament.id as id,temperament._name as name,temperament._added_by as addedBy,temperament._data as data from temperaments_db as temperament',
                    'checkList' => [
                        'temperamentId' => 'temperament.id',
                        'temperamentName' => 'temperament._name',
                        'totalProfessions' => '(select count(id) from temperaments_professions where _temperament_id = temperament.id)'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$temperament){
                        $temperament['id'] = $this->getUtils()->getHashOfData($temperament['id']);
                        $temperament['data'] = \json_decode($temperament['data'],true);
                    }
                    return $result;
                }

                return [];
            break;

            case 'temperament-data':
                $temperamentId = $this->getUtils()::getDataFromArray($data,'temperamentId');
                $query = 'select temperament.id as id,temperament._name as name,temperament._added_by as addedBy,temperament._data as data from temperaments_db as temperament where temperament.id = :temperamentId limit 1';
                $data = [
                    'temperamentId' => &$temperamentId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    $result[0]['id'] = $this->getUtils()->getHashOfData($result[0]['id']);
                    $result[0]['data'] = json_decode($result[0]['data'],true);
                    return $result[0];
                }
                return [];
            break;

            case 'temperament-professions-list':
                $temperamentId = $this->getUtils()::getDataFromArray($data,'temperamentId');
                $query = 'select profession.id as id,profession._name as name,pointer.id as linkedId,pointer._added_by as linkedBy,pointer._data as linkedData from temperaments_professions as pointer join professions_db as profession on (pointer._profession_id = profession.id) where pointer._temperament_id = :temperamentId order by pointer._order asc, pointer.id asc';
                $data = [
                    'temperamentId' => &$temperamentId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    foreach($result as &$item){
                        $item['id'] = $this->getUtils()->getHashOfData($item['id']);
                        $item['linkedId'] = $this->getUtils()->getHashOfData($item['linkedId']);
                    }
                    return $result;
                }
                return [];
            break;

            case 'professions-count':
                $localData = [
                    'query' => 'select count(profession.id) as totalProfessions from professions_db as profession',
                    'checkList' => [
                        'professionId' => 'profession.id',
                        'professionName' => 'profession._name',
                        'totalDisciplines' => '(select count(id) from disciplines_professions where _profession_id = profession.id)'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalProfessions'];
                }

                return 0;
            break;

            case 'profession-id-from-name':
                $name = $this->getUtils()::getDataFromArray($data,'name');
                $result = $db->prepare('select profession.id as id from professions_db as profession where profession._name = :name limit 1')->bind([
                    'name' => &$name
                ])->result();
                if($result){
                    return (int) $result[0]['id'];
                }
                return 0;
            break;

            case 'profession-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from professions_db as profession where profession.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();
                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'profession-disciplines-list':
                $professionId = $this->getUtils()::getDataFromArray($data,'professionId');
                $query = 'select discipline.id as id,discipline._name as name,pointer.id as linkedId,pointer._added_by as linkedBy,pointer._data as linkedData,pointer._weight as weight from disciplines_professions as pointer join disciplines_db as discipline on (pointer._discipline_id = discipline.id) where pointer._profession_id = :professionId order by pointer._order asc, pointer.id asc';
                $data = [
                    'professionId' => &$professionId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    foreach($result as &$item){
                        $item['id'] = $this->getUtils()->getHashOfData($item['id']);
                        $item['linkedId'] = $this->getUtils()->getHashOfData($item['linkedId']);
                    }
                    return $result;
                }
                return [];
            break;

            case 'professions-list':
                $localData = [
                    'query' => 'select profession.id as id,profession._name as name,profession._added_by as addedBy,profession._data as data from professions_db as profession',
                    'checkList' => [
                        'professionId' => 'profession.id',
                        'professionName' => 'profession._name',
                        'totalDisciplines' => '(select count(id) from disciplines_professions where _profession_id = profession.id)'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$profession){
                        $profession['id'] = $this->getUtils()->getHashOfData($profession['id']);
                        $profession['data'] = \json_decode($profession['data'],true);
                    }
                    return $result;
                }

                return [];
            break;

            case 'profession-data':
                $professionId = $this->getUtils()::getDataFromArray($data,'professionId');
                $query = 'select profession.id as id,profession._name as name,profession._art_weight as artWeight, profession._science_weight as scienceWeight,profession._added_by as addedBy,profession._data as data from professions_db as profession where profession.id = :professionId limit 1';
                $data = [
                    'professionId' => &$professionId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    $result[0]['id'] = $this->getUtils()->getHashOfData($result[0]['id']);
                    return $result[0];
                }
                return [];
            break;

            case 'disciplines-count':
                $localData = [
                    'query' => 'select count(discipliine.id) as totalDisciplines from disciplines_db as discipline',
                    'checkList' => [
                        'disciplineId' => 'discipline.id',
                        'disciplineName' => 'discipline._name',
                        'totalProfessions' => '(select count(id) from disciplines_professions where _discipline_id = discipline.id)'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalDisciplines'];
                }

                return 0;
            break;

            case 'discipline-id-from-name':
                $name = $this->getUtils()::getDataFromArray($data,'name');
                $result = $db->prepare('select discipline.id as id from disciplines_db as discipline where discipline._name = :name limit 1')->bind([
                    'name' => &$name
                ])->result();
                if($result){
                    return (int) $result[0]['id'];
                }
                return 0;
            break;

            case 'discipline-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from disciplines_db as discipline where discipline.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();
                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'disciplines-list':
                $localData = [
                    'query' => 'select discipline.id as id,discipline._name as name,discipline._added_by as addedBy,discipline._data as data from disciplines_db as discipline',
                    'checkList' => [
                        'disciplineId' => 'discipline.id',
                        'disciplineName' => 'discipline._name',
                        'totalProfessions' => '(select count(id) from disciplines_professions where _discipline_id = discipline.id)'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$discipline){
                        $discipline['id'] = $this->getUtils()->getHashOfData($discipline['id']);
                        $discipline['data'] = \json_decode($discipline['data'],true);
                    }
                    return $result;
                }

                return [];
            break;

            case 'discipline-data':
                $disciplineId = $this->getUtils()::getDataFromArray($data,'disciplineId');
                $query = 'select discipline.id as id,discipline._name as name,discipline._added_by as addedBy,discipline._data as data from disciplines_db as discipline where discipline.id = :disciplineId limit 1';
                $data = [
                    'disciplineId' => &$disciplineId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    $result[0]['id'] = $this->getUtils()->getHashOfData($result[0]['id']);
                    return $result[0];
                }
                return [];
            break;

            case 'discipline-professions-list':
                $disciplineId = $this->getUtils()::getDataFromArray($data,'disciplineId');
                $query = 'select profession.id as id,profession._name as name,pointer.id as linkedId,pointer._added_by as linkedBy,pointer._data as linkedData,pointer._weight as weight from disciplines_professions as pointer join professions_db as profession on (pointer._profession_id = profession.id) where pointer._discipline_id = :disciplineId order by pointer._order asc, pointer.id asc';
                $data = [
                    'disciplineId' => &$disciplineId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    foreach($result as &$item){
                        $item['id'] = $this->getUtils()->getHashOfData($item['id']);
                        $item['linkedId'] = $this->getUtils()->getHashOfData($item['linkedId']);
                    }
                    return $result;
                }
                return [];
            break;

            case 'subjects-count':
                $localData = [
                    'query' => 'select count(subject.id) as totalSubjects from subjects_db as subject',
                    'checkList' => [
                        'subjectId' => 'subject.id',
                        'subjectName' => 'subject._name',
                        'totalProfessions' => '(select count(id) from subjects_professions where _subject_id = subject.id)'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalSubjects'];
                }

                return 0;
            break;

            case 'subject-id-from-name':
                $name = $this->getUtils()::getDataFromArray($data,'name');
                $result = $db->prepare('select subject.id as id from subjects_db as subject where subject._name = :name limit 1')->bind([
                    'name' => &$name
                ])->result();
                if($result){
                    return (int) $result[0]['id'];
                }
                return 0;
            break;

            case 'subject-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from subjects_db as subject where subject.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();
                if($result){
                    return (int) $id;
                }
                return 0;
            break;

            case 'subjects-list':
                $from = $this->getUtils()::getDataFromArray($data,'from') ?: 0;
                $limit = $this->getUtils()::getDataFromArray($data,'limit') ?: 50;
                $search = $this->getUtils()::getDataFromArray($data,'search');
                $searchKey = 'subject_search_parameter';

                if($search){
                    $validatorRules = [
                        $searchKey => 'min_len,3|max_len,255'
                    ];

                    $filterRules = [
                        $searchKey => 'trim|sanitize_string'
                    ];

                    $validator->validation_rules($validatorRules);
                    $validator->filter_rules($filterRules);
                    $validatedData = $validator->run([
                        $searchKey => $search
                    ]);

                    if($validatedData === false){
                        return [];
                    }
                    $search = $this->getUtils()::getDataFromArray($validatedData,$searchKey);
                }

                $query = 'select subject.id as id,subject._name as name,subject._added_by as addedBy,subject._data as data from subjects_db as subject ';
                $data = [
                    'from' => &$from,
                    'limit' => &$limit
                ];

                if($search){
                    $query .= 'where subject._name like concat("%":search"%") ';
                    $data['search'] = &$search;
                }

                $query .= 'order by subject._name asc limit :limit offset :from';
                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    foreach($result as &$item){
                        $item['id'] = $this->getUtils()->getHashOfData($item['id']);
                    }
                    return $result;
                }
                return [];
            break;

            case 'subject-data':
                $subjectId = $this->getUtils()::getDataFromArray($data,'subjectId');
                $query = 'select subject.id as id,subject._name as name,subject._added_by as addedBy,subject._data as data from subjects_db as subject where subject.id = :subjectId limit 1';
                $data = [
                    'subjectId' => &$subjectId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    $result[0]['id'] = $this->getUtils()->getHashOfData($result[0]['id']);
                    return $result[0];
                }
                return [];
            break;

            case 'subject-professions-list':
                $subjectId = $this->getUtils()::getDataFromArray($data,'subjectId');
                $query = 'select profession.id as id,profession._name as name,pointer.id as linkedId,pointer._added_by as linkedBy,pointer._data as linkedData,pointer._weight as weight from subjects_professions as pointer join professions_db as profession on (pointer._profession_id = profession.id) where pointer._subject_id = :subjectId order by pointer._order asc, pointer.id asc';
                $data = [
                    'subjectId' => &$subjectId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    foreach($result as &$item){
                        $item['id'] = $this->getUtils()->getHashOfData($item['id']);
                        $item['linkedId'] = $this->getUtils()->getHashOfData($item['linkedId']);
                    }
                    return $result;
                }
                return [];
            break;

            case 'faqs-count':
                $localData = [
                    'query' => 'select count(faq.id) as totalFaq from faqs as faq',
                    'checkList' => [
                        'faqId' => 'faq.id',
                        'faqQuestion' => 'faq._question',
                        'faqAnswer' => 'event._answer',
                        'faqDate' => 'event._date'
                    ]
                ];

                $result = $fetch($localData,[
                    'ensureSingleResult' => 1
                ]);

                if($result){
                    return $result[0]['totalFaq'];
                }

                return 0;
            break;

            case 'faq-id-from-id':
                $id = $this->getUtils()::getDataFromArray($data,'id');
                $result = $db->prepare('select 1 from faqs as faq where faq.id = :id limit 1')->bind([
                    'id' => &$id
                ])->result();

                if($result){
                    return (int) $id;
                }

                return 0;
            break;

            case 'faqs-list':
                $localData = [
                    'query' => 'select faq.id as id,faq._question as question,faq._answer as answer from faqs as faq',
                    'checkList' => [
                        'faqId' => 'faq.id',
                        'faqQuestion' => 'faq._question',
                        'faqAnswer' => 'faq._answer',
                        'faqDate' => 'faq._date'
                    ]
                ];

                $result = $fetch($localData);
                if($result){
                    foreach($result as &$data){
                        $data['id'] = $this->getUtils()->getHashOfData($data['id']);
                    }
                    return $result;
                }

                return [];
            break;

            case 'faq-data':
                $faqId = $this->getUtils()::getDataFromArray($data,'faqId');
                $query = 'select faq.id as id,faq._question as question,faq._answer as answer,faq._data as data,faq._date as date from faqs as faq where faq.id = :faqId limit 1';
                $data = [
                    'faqId' => &$faqId
                ];

                $result = $db->prepare($query)->bind($data)->result();
                if($result){
                    $result[0]['id'] = $this->getUtils()->getHashOfData($result[0]['id']);
                    $result[0]['data'] = \json_decode($result[0]['data'],true);
                    return $result[0];
                }
                return [];
            break;
        }
    }
}