## EOS Store Storage

To enable EOS Storga as primary storage for ownCloud you need to add this
configuration option to your configuration file.

```
  'eosstore' => [
    'class' => 'OC\Files\EosStore\EosStoreStorage',
    'arguments' => [
      'storageid' => 'eosuser',
        'instanceurl' => 'eosexample.example.com',
        'homeprefix' => '/eos/example',
    ],
```
