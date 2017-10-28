<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
*/

namespace CharlotteDunois\Yasmin\Traits;

/**
 * The trait all guild channels use.
 */
trait GuildChannelTrait {
    abstract function __get($name);
    
    /**
     * Creates an invite. Options are as following (all are optional).
     *
     *  array(
     *    'maxAge' => int,
     *    'maxUses' => int, (0 = unlimited)
     *    'temporary' => bool,
     *    'unique' => bool
     *  )
     *
     * @param array $options
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Models\Invite>
     */
    function createInvite(array $options = array()) {
        $data = array(
            'max_uses' => $options['maxUses'] ?? 0,
            'temporary' => $options['temporary'] ?? false,
            'unique' => $options['unique'] ?? false
        );
        
        if(isset($options['maxAge'])) {
            $data['max_age'] = $options['maxAge'];
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($data) {
            $this->client->apimanager()->endpoints->channel->createChannelInvite($this->id, $data)->then(function ($data) use ($resolve) {
                $invite = new \CharlotteDunois\Yasmin\Models\Invite($this->client, $data);
                $resolve($invite);
            }, $reject);
        }));
    }
    
    /**
     * Clones a guild channel.
     * @param string  $name             Optional name for the new channel, otherwise it has the name of this channel.
     * @param bool    $withPermissions  Whether to clone the channel with this channel's permission overwrites
     * @param bool    $withTopic        Whether to clone the channel with this channel's topic.
     * @param string  $reason
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface>
     */
    function clone(string $name = null, bool $withPermissions = true, bool $withTopic = true, string $reason = '') {
        $data = array(
            'name' => (string) (!empty($name) ? $name : $this->name),
            'type' => (int) (\array_keys(\CharlotteDunois\Yasmin\Constants::CHANNEL_TYPES)[\array_search($this->type, \CharlotteDunois\Yasmin\Constants::CHANNEL_TYPES)])
        );
        
        if($withPermissions) {
            $data['permissions_overwrites'] = $this->permissionsOverwrites->all();
        }
        
        if($withTopic) {
            $data['topic'] = $this->topic;
        }
        
        if($this->parentID) {
            $data['parent_id'] = $this->parentID;
        }
        
        if($this->type === 'voice') {
            $data['bitrate'] = $this->bitrate;
            $data['user_limit'] = $this->userLimit;
        } else {
            $data['nsfw'] = $this->nsfw;
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($data, $reason) {
            $this->client->apimanager()->endpoints->guild->createGuildChannel($this->guild->id, $data, $reason)->then(function ($data) use ($resolve) {
                $channel = \CharlotteDunois\Yasmin\Models\GuildChannel::factory($this->client, $this->guild, $data);
                $resolve($channel);
            }, $reject);
        }));
    }
     
    /**
     * Edits the channel. Options are as following (at least one is required).
     *
     *  array(
     *    'name' => string,
     *    'position' => int,
     *    'topic' => string,
     *    'bitrate' => int, (voice channels only)
     *    'userLimit' => int (voice channels only)
     *  )
     *
     * @param array   $options
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     * @throws \InvalidArgumentException
     */
    function edit(array $options, string $reason = '') {
        if(empty($options)) {
            throw new \InvalidArgumentException('Can not edit channel with zero information');
        }
        
        $data = array();
        
        if(isset($options['name'])) {
            $data['name'] = $options['name'];
        }
        
        if(isset($options['position'])) {
            $data['position'] = $options['position'];
        }
        
        if(isset($options['bitrate'])) {
            if($this instanceof \CharlotteDunois\Yasmin\Models\TextChannel) {
                throw new \InvalidArgumentException('Can not set channel bitrate of a text channel');
            }
            
            $data['bitrate'] = $options['bitrate'];
        }
        
        if(isset($options['userLimit'])) {
            if($this instanceof \CharlotteDunois\Yasmin\Models\TextChannel) {
                throw new \InvalidArgumentException('Can not set channel user limit of a text channel');
            }
            
            $data['user_limit'] = $options['userLimit'];
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($data, $reason) {
            $this->client->apimanager()->endpoints->channel->editChannel($this->id, $data, $reason)->then(function ($data) use ($resolve) {
                $this->_patch($data);
                $resolve();
            }, $reject);
        }));
    }
    
    /**
     * Deletes the channel.
     * @param string  $reason
     * @return \React\Promise\Promise<void>
     */
    function delete(string $reason = '') {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($reason) {
            $this->client->apimanager()->endpoints->channel->deleteChannel($this->id, $reason)->then(function () use ($resolve) {
                $resolve();
            }, $reject);
        }));
    }
    
    /**
     * Fetches all invites of this channel.
     * @return \React\Promise\Promise<\CharlotteDunois\Collect\Collection<\CharlotteDunois\Yasmin\Models\Invite>>
     */
    function fetchInvites() {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
            $this->client->apimanager()->endpoints->channel->getChannelInvites($this->id)->then(function ($data) use ($resolve) {
                $collection = new \CharlotteDunois\Yasmin\Models\Collection();
                
                foreach($data as $invite) {
                    $inv = new \CharlotteDunois\Yasmin\Models\Invite($this->client, $invite);
                    $collection->set($inv->code, $inv);
                }
                
                $resolve($collection);
            }, $reject);
        }));
    }
    
    /**
     * Returns the permissions for the given member.
     * @param \CharlotteDunois\Yasmin\Models\GuildMember|string  $member
     * @return \CharlotteDunois\Yasmin\Models\Permissions
     * @throws \InvalidArgumentException
     */
    function permissionsFor($member) {
        $member = $this->guild->members->resolve($member);
        
        if($member->id === $this->guild->ownerID) {
            return (new \CharlotteDunois\Yasmin\Models\Permissions(\CharlotteDunois\Yasmin\Models\Permissions::ALL));
        }
        
        $maxBitfield = $member->roles->map(function ($role) {
            return $role->permissions->bitfield;
        })->max();
        
        $permissions = new \CharlotteDunois\Yasmin\Models\Permissions($maxBitfield);
        
        if($permissions->has('ADMINISTRATOR')) {
            return (new \CharlotteDunois\Yasmin\Models\Permissions(\CharlotteDunois\Yasmin\Models\Permissions::ALL));
        }
        
        $overwrites = $this->overwritesFor($member);
        
        if($overwrites['everyone']) {
            if($overwrites['everyone']->allow->bitfield) {
                $permissions->add($overwrites['everyone']->allow->bitfield);
            }
            
            if($overwrites['everyone']->deny->bitfield) {
                $permissions->remove($overwrites['everyone']->deny->bitfield);
            }
        }
        
        if($overwrites['member']) {
            if($overwrites['member']->allow) {
                $permissions->add($overwrites['member']->allow->bitfield);
            }
            
            if($overwrites['member']->deny) {
                $permissions->remove($overwrites['member']->deny->bitfield);
            }
        }
        
        foreach($overwrites['roles'] as $role) {
            if($role->allow->bitfield) {
                $permissions->add($role->allow->bitfield);
            }
            if($role->deny->bitfield) {
                $permissions->remove($role->deny->bitfield);
            }
        }
        
        return $permissions;
    }
    
    /**
     * Returns the permissions overwrites for the given member.
     * @param \CharlotteDunois\Yasmin\Models\GuildMember|string  $member
     * @return array
     * @throws \InvalidArgumentException
     */
    function overwritesFor($member) {
        $member = $this->guild->members->resolve($member);
        
        $everyoneOverwrites = null;
        $memberOverwrites = null;
        $rolesOverwrites = array();
        
        foreach($this->permissionsOverwrites->all() as $overwrite) {
            if($overwrite->id === $this->guild->id) {
                $everyoneOverwrites = $overwrite;
            } elseif($overwrite->id === $member->id) {
                $memberOverwrites = $overwrite;
            } elseif($member->roles->has($overwrite->id)) {
                $rolesOverwrites[] = $overwrite;
            }
        }
        
        return array(
            'everyone' => $everyoneOverwrites,
            'member' => $memberOverwrites,
            'roles' => $rolesOverwrites
        );
    }
    
    /**
     * Overwrites the permissions for a member or role in this channel.
     * @param \CharlotteDunois\Yasmin\Models\GuildMember|\CharlotteDunois\Yasmin\Models\Role|string  $memberOrRole  The member or role.
     * @param \CharlotteDunois\Yasmin\Models\Permissions|int                                             $allow         Which permissions should be allowed?
     * @param \CharlotteDunois\Yasmin\Models\Permissions|int                                             $deny          Which permissions should be denied?
     * @param string                                                                                         $reason        The reason for this.
     * @return \React\Promise\Promise<\CharlotteDunois\Yasmin\Models\PermissionOverwite>
     * @throws \InvalidArgumentException
     */
    function overwritePermissions($memberOrRole, $allow, $deny = 0, string $reason = '') {
        $options = array();
        
        try {
            $memberOrRole = $this->guild->members->resolve($memberOrRole);
            $options['type'] = 'user';
        } catch(\InvalidArgumentException $e) {
            $memberOrRole = $this->guild->roles->resolve($memberOrRole);
            $options['type'] = 'role';
        }
        
        if(!\is_int($allow) && !($allow instanceof \CharlotteDunois\Yasmin\Models\Permissions)) {
            throw new \InvalidArgumentException('Allow has to be an int or instanceof Permissions');
        }
        
        if(!\is_int($deny) && !($deny instanceof \CharlotteDunois\Yasmin\Models\Permissions)) {
            throw new \InvalidArgumentException('Deny has to be an int or instanceof Permissions');
        }
        
        $options['allow'] = $allow;
        $options['deny'] = $deny;
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($memberOrRole, $options, $reason) {
            $this->client->apimanager()->endpoints->guild->editChannelPermissions($this->id, $memberOrRole->id, $options, $reason)->then(function () use ($memberOrRole, $options, $resolve) {
                $options['id'] = $memberOrRole->id;
                
                $overwrite = new \CharlotteDunois\Yasmin\Models\PermissionOverwrite($this->client, $this, $options);
                $this->permissionsOverwrites->set($overwrite->id, $overwrite);
                
                $resolve($overwrite);
            }, $reject);
        }));
    }
    
    /**
     * Locks in the permission overwrites from the parent channel.
     * @param string  $reason  The reason for this.
     * @return \React\Promise\Promise<this>
     * @throws \BadMethodCallException
     */
    function lockPermissions(string $reason = '') {
        if(!$this->__get('parent')) {
            throw new \BadMethodCallException('This channel does not have a parent');
        }
        
        $overwrites = \array_values($this->__get('parent')->permissionsOverwrites->map(function ($overwrite) {
            return array(
                'id' => $overwrite->id,
                'type' => $overwrite->type,
                'allow' => $overwrite->allow->bitfield,
                'deny' => $overwrite->deny->bitfield
            );
        })->all());
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($overwrites, $reason) {
            $promises = array();
            foreach($overwrites as $overwrite) {
                $promises[] = $this->client->apimanager()->endpoints->guild->editChannelPermissions($this->id, $overwrite['id'], $overwrite, $reason);
            }
            
            \React\Promise\all($promises)->then(function () use ($resolve) {
                $resolve();
            }, $reject);
        }));
    }
    
    /**
     * Sets the name of the channel.
     * @param string  $name
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     * @throws \InvalidArgumentException
     */
    function setName(string $name, string $reason = '') {
        return $this->edit(array('name' => $name), $reason);
    }
    
    /**
     * Sets the position of the channel.
     * @param int     $position
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     * @throws \InvalidArgumentException
     */
    function setPosition(int $position, string $reason = '') {
        if($position < 0) {
            throw new \InvalidArgumentException('Position can not be below 0');
        }
        
        $newPositions = array();
        $newPositions[] = array('id' => $this->id, 'position' => $position);
        
        $count = $this->guild->channels->count();
        $channels = $this->guild->channels->sort(function ($a, $b) {
            return $a->position <=> $b->position;
        })->all();
        
        $pos = 0;
        for($i = 0; $i < $count; $i++) {
            if($pos === $position) {
                $pos++;
            }
            
            $channel = $channels[$i];
            
            if($pos === $channel->position) {
                continue;
            }
            
            $newPositions[] = array('id' => $channel, 'position' => $pos);
            $pos++;
        }
        
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($newPositions, $reason) {
            $this->client->apimanager()->endpoints->guild->modifyGuildChannelPositions($this->guild->id, $newPositions, $reason)->then(function () use ($resolve) {
                $resolve();
            }, $reject);
        }));
    }
    
    /**
     * Sets the topic of the channel.
     * @param string  $topic
     * @param string  $reason
     * @return \React\Promise\Promise<this>
     * @throws \InvalidArgumentException
     */
    function setTopic(string $topic, string $reason = '') {
        return $this->edit(array('topic' => $topic), $reason);
    }
}