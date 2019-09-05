<?php

namespace Scit\Users\Student;

class Manager extends \Scit\General\Templates\General{
    public function takeAcademicTest(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $validatorRules = [
            'science_test' => 'required|numeric',
            'creative_test' => 'required|numeric',
            'thinker_test' => 'required|numeric',
            'adventureous_test' => 'required|numeric',
            'best_six_subjects' => 'required|valid_array_size_greater,6|valid_array_size_lesser,6'
        ];

        $filterRules = [
            'science_test' => 'trim|sanitize_numbers',
            'creative_test' => 'trim|sanitize_numbers',
            'thinker_test' => 'trim|sanitize_numbers',
            'adventureous_test' => 'trim|sanitize_numbers'
        ];

        $validator->validation_rules($validatorRules);
        $validator->filter_rules($filterRules);
        $validatedData = $validator->run($request->get());

        if($validatedData === false){
            return [
                'status' => 'error',
                'response' => 'Invalid input parameters.. please follow stipulated instructions'
            ];
        }

        $science = $this->getUtils()::getDataFromArray($validatedData,'science_test');
        $creative = $this->getUtils()::getDataFromArray($validatedData,'creative_test');
        $thinker = $this->getUtils()::getDataFromArray($validatedData,'thinker_test');
        $adventure = $this->getUtils()::getDataFromArray($validatedData,'adventureous_test');
        $bestSixSubjects = $request->get('best_six_subjects');
        $bestSixSubjectsScore = 0;

        foreach($bestSixSubjects as $key => $score){
            $bestSixSubjectsScore = ($bestSixSubjectsScore + (int) $score);
        }

        $output = [
            'isValid' => 0,
            'contactCouncellor' => 0,
            'isScience' => 0,
            'isArt' => 0,
            'level' => $this->getSession()->get('studentData-->level'),
            'canContinueTest' => isset(array_flip([
                'sss2',
                'sss3'
            ])[$this->getSession()->get('studentData-->level')])
        ];

        if($science){
            if($bestSixSubjectsScore >= 23){
                $this->getUtils()::setDataInArray($output,'isScience',1);
                $this->getUtils()::setDataInArray($output,'isValid',1);
                $this->getUtils()::setDataInArray($output,'isArt',0);

                if($creative && $thinker && $adventure){
                    $this->getUtils()::setDataInArray($output,'isTechnologist',1);
                }
            }else{
                $this->getUtils()::setDataInArray($output,'isValid',0);
            }
        }else{
            if($bestSixSubjectsScore < 23){
                $this->getUtils()::setDataInArray($output,'isArt',1);
                $this->getUtils()::setDataInArray($output,'isValid',1);
                $this->getUtils()::setDataInArray($output,'isScience',0);
            }else{
                $this->getUtils()::setDataInArray($output,'isValid',0);
            }
        }

        if(!$output['isValid']){
            $this->getUtils()::setDataInArray($output,'contactCouncellor',1);
            return [
                'status' => 'error',
                'data' => $output,
                'response' => 'An error occurred.. Invalid data from user'
            ];
        }

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
                    insert into students_tests (_student_id,_type,_id_result_1,_id_result_2,_id_result_3,_id_result_4,_text_result_1,_text_result_2) values (:studentId,:testType,:isScience,:isArt,:isTechnologist,:isValid,:data,:level);

                    if row_count() then
                        select '{"status":"ok","response":"Academic test complete..."}' as response;
                        leave `inner_process`;
                    end if;

                    select '{"status":"error","response":"An unknown error occured please try again later"}' as response;
                    leave `inner_process`;
                end;
            end;
query;

        $data = [
            'studentId' => $this->getSession()->get('studentData-->id'),
            'testType' => 'academic',
            'isScience' => &$output['isScience'],
            'isArt' => &$output['isArt'],
            'isTechnologist' => &$output['isTechnologist'],
            'isValid' => &$output['isValid'],
            'level' => &$output['level']
        ];

        $data['data'] = json_encode($data);

        $result = $this->getDatabase()->prepare($query)->bind($data)->result(true);

        if($result){
            $data['data'] = null;
            $this->getSession()->set('studentData-->testData-->academic',$data);
            $result = json_decode($result[0]['response'],true);
            if(is_array($result)){
                return [
                    'status' => 'ok',
                    'response' => array_merge($output,[
                        'responseText' => $this->processSubjectTestData($output)
                    ])
                ];
            }
            return $this->getUtils()::getResponseFor('malformed-db-response');
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function takeTemperamentTest(){
        $request = $this->getUtils()->init('General-Request');
        $db = $this->getDatabase();

        $temperamentTest = $request->get('temperament_test');
        if(!(is_array($temperamentTest) && isset($temperamentTest['part_a']) && is_array($temperamentTest['part_a']) && (count($temperamentTest['part_a']) === 5) && isset($temperamentTest['part_b']) && is_array($temperamentTest['part_b']) && (count($temperamentTest['part_b']) === 5) && isset($temperamentTest['part_c']) && is_array($temperamentTest['part_c']) && (count($temperamentTest['part_c']) === 6) && isset($temperamentTest['part_d']) && is_array($temperamentTest['part_d']) && (count($temperamentTest['part_d']) === 5))){
            return [
                'status' => 'error',
                'response' => 'You are required to select at least one option from each queestion.'
            ];
        }

        $output = [
            'isValid' => 0,
            'level' => $this->getSession()->get('studentData-->level'),
            'part_a' => [
                'score' => 0,
                'value' => null
            ],
            'part_b' => [
                'score' => 0,
                'value' => null
            ],
            'part_c' => [
                'score' => 0,
                'value' => null
            ],
            'part_d' => [
                'score' => 0,
                'value' => null
            ],
            'value' => ''
        ];

        foreach($temperamentTest['part_a'] as $v){
            $output['part_a']['score'] = ($output['part_a']['score'] + ((int) $v));
        }

        foreach($temperamentTest['part_b'] as $v){
            $output['part_b']['score'] = ($output['part_b']['score'] + ((int) $v));
        }

        foreach($temperamentTest['part_c'] as $v){
            $output['part_c']['score'] = ($output['part_c']['score'] + ((int) $v));
        }

        foreach($temperamentTest['part_d'] as $v){
            $output['part_d']['score'] = ($output['part_d']['score'] + ((int) $v));
        }

        if(($output['part_a']['score'] >= 5) && ($output['part_a']['score'] <= 25)){
            if($output['part_a']['score'] < 15){
                $output['part_a']['value'] = 'i';
            }else{
                $output['part_a']['value'] = 'e';
            }
        }

        if(($output['part_b']['score'] >= 5) && ($output['part_b']['score'] <= 25)){
            if($output['part_b']['score'] < 15){
                $output['part_b']['value'] = 'n';
            }else{
                $output['part_b']['value'] = 's';
            }
        }

        if(($output['part_c']['score'] >= 5) && ($output['part_c']['score'] <= 30)){
            if($output['part_c']['score'] < 15){
                $output['part_c']['value'] = 'f';
            }else{
                $output['part_c']['value'] = 't';
            }
        }

        if(($output['part_d']['score'] >= 5) && ($output['part_d']['score'] <= 25)){
            if($output['part_d']['score'] < 15){
                $output['part_d']['value'] = 'p';
            }else{
                $output['part_d']['value'] = 'j';
            }
        }

        if($output['part_a']['value'] && $output['part_b']['value'] && $output['part_c']['value'] && $output['part_d']['value']){
            $output['isValid'] = 1;
            $output['value'] = $output['part_a']['value'].$output['part_b']['value'].$output['part_c']['value'].$output['part_d']['value'];
        }else{
            return [
                'status' => 'error',
                'response' => 'Data error... please try again'
            ];
        }

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
                    insert into students_tests (_student_id,_type,_text_result_1,_text_result_2,_text_result_3,_id_result_4) values (:studentId,:testType,:data,:level,:value,:isValid);

                    if row_count() then
                        select '{"status":"ok","response":"Temperament test complete..."}' as response;
                        leave `inner_process`;
                    end if;

                    select '{"status":"error","response":"An unknown error occured please try again later"}' as response;
                    leave `inner_process`;
                end;
            end;
query;

        $data = [
            'studentId' => $this->getSession()->get('studentData-->id'),
            'testType' => 'temperament',
            'value' => &$output['value'],
            'isValid' => &$output['isValid'],
            'level' => &$output['level']
        ];

        $data['data'] = json_encode($data);
        $result = $this->getDatabase()->prepare($query)->bind($data)->result(true);

        if($result){
            $data['data'] = null;
            $this->getSession()->set('studentData-->testData-->temperament',$data);
            $result = json_decode($result[0]['response'],true);
            if(is_array($result)){
                return [
                    'status' => 'ok',
                    'response' => array_merge($output,[
                        'responseText' => 'Temperament data saved succesfully'
                    ])
                ];
            }
            return $this->getUtils()::getResponseFor('malformed-db-response');
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function takeBestSubjectsTest(){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        $bestSubjects = $request->get('bestSubjects');
        if(!(is_array($bestSubjects) && (count($bestSubjects) === 2))){
            return [
                'status' => 'error',
                'response' => 'Invalid Request'
            ];
        }

        $subject1 = $this->getUtils()->getDataOfHash($bestSubjects[0]);
        $subject2 = $this->getUtils()->getDataOfHash($bestSubjects[1]);

        if(!(is_numeric($subject1) && is_numeric($subject2))){
            return [
                'status' => 'error',
                'response' => 'Invalid Request.'
            ];
        }

        $output = [
            'isValid' => 1,
            'value' => json_encode([$subject1,$subject2]),
            'subject1' => $subject1,
            'subject2' => $subject2,
            'studentId' => $this->getSession()->get('studentData-->id'),
            'testType' => 'bestSubjects',
            'level' => $this->getSession()->get('studentData-->level')
        ];

        $data = json_encode($output);
        $output['data'] = $data;

        $query = <<<'query'
            begin not atomic
                `inner_process`: begin
                    insert into students_tests (_student_id,_type,_text_result_1,_text_result_2,_text_result_3,_id_result_1,_id_result_2,_id_result_4) values (:studentId,:testType,:data,:level,:value,:subject1,:subject2,:isValid);

                    if row_count() then
                        select '{"status":"ok","response":"Best subject Test completed succesfully..."}' as response;
                        leave `inner_process`;
                    end if;

                    select '{"status":"error","response":"An unknown error occured please try again later"}' as response;
                    leave `inner_process`;
                end;
            end;
query;

        $result = $this->getDatabase()->prepare($query)->bind($output)->result(false);

        if($result){
            $output['data'] = null;
            $this->getSession()->set('studentData-->testData-->bestSubjects',$output);
            
            $result = json_decode($result[0]['response'],true);
            if(is_array($result)){
                return [
                    'status' => 'ok',
                    'response' => array_merge($output,[
                        'responseText' => 'Best subjects data saved succesfully'
                    ])
                ];
            }
            return $this->getUtils()::getResponseFor('malformed-db-response');
        }
        return $this->getUtils()::getResponseFor('invalid-db-response');
    }

    public function getTemperamentData(){
        $db = $this->getDatabase();
        $temperament = $this->getSession()->get('studentData-->testData-->temperament-->value');

        if(strlen($temperament)){
            $result = $db->prepare('select temperament._data as data from temperaments_db as temperament where temperament._name = :temperament order by temperament.id desc limit 1')->bind([
                'temperament' => &$temperament
            ])->result(true);

            if($result){
                $data = json_decode($result[0]['data'],true);
                if(is_array($data)){
                    return $data;
                }
            }
        }

        return [];
    }

    public function getProfessionsData(array $data = []){
        $db = $this->getDatabase();

        $academicResult = $this->getSession()->get('studentData-->testData-->academic');
        $temperamentResult = $this->getSession()->get('studentData-->testData-->temperament');
        $bestSubjectsResult = $this->getSession()->get('studentData-->testData-->bestSubjects');

        $orderByQuery = '';
        if($this->getUtils()::getDataFromArray($academicResult,'isValid')){
            if($this->getUtils()::getDataFromArray($academicResult,'isScience')){
                $orderByQuery = 'professionScienceWeight desc';
            }else{
                $orderByQuery = 'professionArtWeight desc';
            }
        }

        $limit = (int) $this->getUtils()::getDataFromArray($data,'limit') ?: 50;
        $from = (int) $this->getUtils()::getDataFromArray($data,'from') ?: 0;

        $query = <<<"query"
            select profession.id as professionId,profession._name as professionName,profession._science_weight as professionScienceWeight,profession._art_weight as professionArtWeight from temperaments_db as temperament join temperaments_professions as tempProfLink on (temperament.id = tempProfLink._temperament_id) join professions_db as profession on (tempProfLink._profession_id = profession.id) join subjects_professions as sub1ProfLink on (profession.id = sub1ProfLink._profession_id) join subjects_db as subject1 on (sub1ProfLink._subject_id = subject1.id) join subjects_professions as sub2ProfLink on (profession.id = sub2ProfLink._profession_id) join subjects_db as subject2 on (sub2ProfLink._subject_id = subject2.id) where temperament._name = :temperament and subject1.id = :subject1 and subject2.id = :subject2 order by {$orderByQuery} limit :limit offset :from
query;

        $data = [
            'temperament' => $this->getUtils()::getDataFromArray($temperamentResult,'value'),
            'subject1' => $this->getUtils()::getDataFromArray($bestSubjectsResult,'subject1'),
            'subject2' => $this->getUtils()::getDataFromArray($bestSubjectsResult,'subject2'),
            'limit' => $limit,
            'from' => $from
        ];

        $result = $db->prepare($query)->bind($data)->result(false);

        if($result){
            $query = <<<'query'
                begin not atomic
                    declare outData longtext;
                    declare levelData longtext;
                    declare currData longtext;
                    declare totalCount int default 0;
                    declare processedCount int default 0;
                    declare professionId int default 0;
                    declare lastProcessedId int default 0;
                    declare isDataSet tinyint default 0;
                    declare hashedId varchar(255) default '';

                    set outData = '{';

                    `inner_process`:begin
                        
query;
            foreach($result as &$resp){
                $professionId = $resp['professionId'];
                $hashedId = $resp['professionId'] = $this->getUtils()->getHashOfData($resp['professionId']);

                $query .= <<<"query"
                        set levelData = '',currData = '',totalCount = 0,processedCount = 0,lastProcessedId = 0,professionId = {$professionId},hashedId = '{$hashedId}';
                        select count(discipProf.id) into totalCount from disciplines_professions as discipProf where discipProf._profession_id = professionId;

                        if totalCount then
                            set levelData = '[';

                            `while_process`:while totalCount > processedCount do
                                set isDataSet = 0;
                                select concat('{"id":',discipline.id,',"name":"',discipline._name,'"}'),discipProf.id,1 into currData,lastProcessedId,isDataSet from disciplines_db as discipline join disciplines_professions as discipProf on (discipProf._discipline_id = discipline.id and discipProf._profession_id = professionId and discipProf.id > lastProcessedId) order by discipProf._weight desc,discipProf._order asc,discipProf.id asc limit 1;
                                
                                if (lastProcessedId and isDataSet) then
                                    set levelData = concat(levelData,if(levelData = '[','',','),currData);                
                                end if;

                                set processedCount = (processedCount + 1);
                            end while `while_process`;

                            set levelData = concat(levelData,']');
                            set outData = concat(outData,if(outData = '{','',','),concat('"',hashedId,'":',levelData));
                        end if;
query;
            }

            $query .= <<<'query'
                        set outData = concat(outData,'}');
                        select outData as response;
                    end;
                end;
query;

            $disciplineData = $db->query($query)->result();
            if($disciplineData){
                $disciplineData = json_decode($disciplineData[0]['response'],true);
                if(is_array($disciplineData)){
                    foreach ($result as &$profession) {
                        $disciplines = $this->getUtils()::getDataFromArray($disciplineData,$profession['professionId']);
                        if($disciplines){
                            foreach($disciplines as &$discipline){
                                $discipline['id'] = $this->getUtils()->getHashOfData($discipline['id']);
                            }
                            $this->getUtils()::setDataInArray($profession,'disciplines',$disciplines);
                        }
                    }
                }
            }
        }else{
            $result = [];
        }

        return $result;
    }

    private function processSubjectTestData(array $data){
        $responseText = [];
        $isValid = $this->getUtils()::getDataFromArray($data,'isValid');
        $isScience = $this->getUtils()::getDataFromArray($data,'isScience');
        $isArt = $this->getUtils()::getDataFromArray($data,'isArt');
        $level = $this->getUtils()::getDataFromArray($data,'level');
        $canContinueTest = $this->getUtils()::getDataFromArray($data,'canContinueTest');
        $isTechnologist = $this->getUtils()::getDataFromArray($data,'isTechnologist');

        if($isValid){
            if($isScience){
                $responseText[] = [
                    'type' => 'success',
                    'text' => 'Congratulations... you will be a very good Science student'
                ];
                if($isTechnologist){
                    $responseText[] = [
                        'type' => 'success',
                        'text' => 'You will also be a very good Technologist'
                    ];
                }
            }else{
                $responseText[] = [
                    'type' => 'success',
                    'text' => 'Congratulations... you will be a very good Art student'
                ];
            }

            if(!$canContinueTest){
                $responseText[] = [
                    'type' => 'info',
                    'text' => 'You cannot process other tests for now....'
                ];
            }
        }

        return $responseText;
    }

    public function get($type,$data = []){
        $request = $this->getUtils()->init('General-Request');
        $validator = $this->getUtils()->init('General-Validator');
        $db = $this->getDatabase();

        switch($type){
            case 'get-academic-test-result':
                
            break;

            case 'get-temperament-test-result':

            break;

            case 'get-professions-list':
                
            break;
        }
    }
}