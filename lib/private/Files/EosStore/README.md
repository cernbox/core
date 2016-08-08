## EOS Store Storage

To enable EOS Storge as primary storage for ownCloud you need to add this
configuration option to your configuration file.

```
  'eosstore' => [
    'class' => 'OC\Files\EosStore\EosStoreStorage',
    'arguments' => [
      'storageid' => 'eosuser',
        'instanceurl' => 'eosexample.example.com',
        'homeprefix' => '/eos/example',
    ],
  ],
```

## BUGS TO TELL OWNCLOUD
- The ICacheEntry does not mandate to implement \ArrayAccess, but is mandatory in order to have our storage implementation.
- After uploading a file, a FileInfo is returned. This FileInfo is created from the metadata returned from ICache:get(),
  but is does not call ICacheEntry methods, but relies on \ArrayAccess. Even more, it relies in returning a parent filed in the 
  array that is not declated in ICacheEntry.

