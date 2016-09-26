
# CERNBox configuration

```
  'eosstore' => [
    'class' => 'OC\CernBox\Storage\Eos\Storage',
  ],
  
  'eosinstances' => [
  
       'eosbackup' => [
           'name' => 'EOS Backup',
           'mgmurl' => 'root://eosbackup.cern.ch',
           'prefix' => '/eos/scratch/user/<letter>/<username>/',
           'metadatadir' => '/eos/scratch/user/.sys.dav.hide#.user.metadata/<letter>/<username>/',
           'recycledir' => '/eos/scratch/proc/recycle',
           'filterregex' => '\\.sys\\.[a-zA-Z0-9_]*#\\.',
           'versionregex' => '\\.sys\\.v#\\.',
           'projectprefix' => '/eos/scratch/projects/',
           'stagingdir' => '/tmp/',
       ], 
  ],
   
  'eoshomedirectoryinstance' => 'eosbackup',
   
  'eoscliretryattempts' => '2',
```
