<?php

namespace Scit\General;

class Permission{
    private $managers,$__data = [];
    
    public function __construct(&$managers){
        $this->managers = &$managers;
    }

    public function getUtils(){
        return $this->managers['general']['utils'];
    }

    private function getDatabase(){
        return $this->getUtils()->init('general-database');
    }

    public function refresh(){
        $this->__data = [];
        return $this;
    }

    private function setPermissionStore($forId){
        $permissions = $this->get($forId);
        $this->__data = $permissions;
        return $this;
    }

    private function isPermittedTo($permission){
        $permissions = false;

        $session = $this->getUtils()->init('General-Session');
        $userId = $session->get('userData-->id') ?: false;
        if(!count($this->__data) || !is_array($this->__data)){
            $this->setPermissionStore($userId);
        }

        $permissions = $this->__data;

        if(!$permissions){
            $permissions = [];
        }

        $flipped = array_flip($permissions);
        if(isset($flipped['all']) || isset($flipped[$permission]) || isset($flipped["{$permission}->all"])){
            return true;
        }else{
            $check = function(array $headings) use(&$check,&$flipped){
                $last = array_pop($headings);
                $headings = implode('->',$headings);
                
                if(strlen($headings)){
                    if(isset($flipped[$headings]) || isset($flipped["{$headings}->all"])){
                        return true;
                    }else{
                        if($headings){
                            return $check(explode('->',$headings));
                        }
                    }
                }
                return false;
            };
            return $check(explode('->',$permission));    
        }
    }

    private function getPermissionDatabase(string $for){
        $permissions = file_get_contents($this->managers['documentRoot'].$this->getUtils()::getDataFromArray($this->managers,"adminSettings-->default-->permissions-->{$for}"));
        $permissions = json_decode($permissions,true);

        if(is_array($permissions)){
            return $permissions;
        }

        return [];
    }

    private function set($permission,$forId){

        $query = <<<'query'
            begin not atomic
            declare permissionsAdded bigint default 0;
            declare permissionId bigint default 0;
query;

        $addPermission = function($permission) use(&$query,$forId){
            $query = <<<"query"
                $query
                set permissionId = 0;
                select permission.id into permissionId from permissions_db as permission where permission._name = '$permission';
                if permissionId then
                    insert into permissions (_permission_id,_for_id) values (permissionId,'$forId');

                    set permissionsAdded = if(last_insert_id(),permissionsAdded + 1,permissionsAdded);
                end if;
query;
        };

        if(is_array($permission) && isset($permission[0])){
            foreach($permission as $item){
                $addPermission($item);
            }
        }elseif(is_string($permission)){
            $addPermission($permission);
        }else{
            return [
                'status' => 'error',
                'response' => 'invalid permission'
            ];
        }

        $query = <<<"query"
            $query
            select permissionsAdded as response;
            end;
query;

        $result = $this->getDatabase()->query($query)->result();
        if($result){
            return [
                'status' => 'ok',
                'permissionAdded' => $result[0]['response']
            ];
        }
        return [
            'status' => 'error',
            'response' => 'Ooops permission could not be added.'
        ];
    }

    private function get($forId){
        $db = $this->getDatabase();
        $query = <<<'query'
            begin not atomic
            declare currId bigint default 0;
            declare _id bigint default 0;
            declare totalAdded bigint default 0;
            declare totalPermission bigint default 0;
            declare totalIteration bigint default 0;
            declare outData longtext;
            declare currData longtext;

            set outData = "[";
            select count(permissionPointer.id) into totalPermission from permissions as permissionPointer where permissionPointer._for_id = :forId;

            loopHolder: while (totalPermission != totalIteration) do
                set currData = '';
                set _id = 0;
                select if(totalAdded > 0,concat(',','"',permission._name,'"'),concat('"',permission._name,'"')),pointer.id into currData,_id from permissions as pointer join permissions_db as permission on pointer._permission_id = permission.id where pointer._for_id = :forId and pointer.id > currId limit 1;

                if (char_length(currData) and _id) then
                    set currId = _id;
                    set outData = concat(outData,currData);
                    set totalAdded = (totalAdded + 1);
                end if;

                set totalIteration = (totalIteration + 1);
            end while loopHolder;

            select concat(outData,']') as permissions;
            end;
query;
        $result = $db->prepare($query)->bind([
            'forId' => $forId
        ])->result(false);

        if($result){
            $result = json_decode($result[0]['permissions'],true);
            if(is_array($result)){
                return $result;
            }
        }
        return [];
    }
}