
# CERNBox configuration

```
  'eosstore' => [
    'class' => 'OC\CernBox\Storage\Eos\Storage',
  ],
  
  'eosinstances' => [
       'eosbackup' => [
           'name' => 'EOS Backup',
           'mgmurl' => 'root://eosbackup.cern.ch',
           'prefix' => '/eos/scratch/user/',
           'metadatadir' => '/eos/scratch/user/.sys.dav.hide#.user.metadata/',
           'recycledir' => '/eos/scratch/proc/recycle',
           'filterregex' => '\\.sys\\.[a-zA-Z0-9_]*#\\.',
           'versionregex' => '\\.sys\\.v#\\.',
           'projectprefix' => '/eos/scratch/project/',
           'stagingdir' => '/tmp/',
       ], 
  ],
   
  'eoshomedirectoryinstance' => 'eosbackup',
  'eoscliretryattempts' => 2,
  
  'user_backends' => array(
    array(
        'class' => 'OC\CernBox\Backends\UserBackend',
        'arguments' => array(),
    ),
  ),
  
  'sharing.managerFactory' => 'OC\CernBox\Share\ProviderFactory',
  
```
