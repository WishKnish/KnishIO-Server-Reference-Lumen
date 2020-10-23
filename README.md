<div style="text-align:center">
  <img src="https://raw.githubusercontent.com/WishKnish/KnishIO-Technical-Whitepaper/master/KnishIO-Logo.png" alt="Knish.IO: Post-Blockchain Platform" />
</div>
<div style="text-align:center">info@wishknish.com | https://wishknish.com</div>

# Welcome to Knish.IO!
This project provides a reference implementation of the [Knish.IO](https://knish.io) PHP Server on the Lumen PHP microframework. Because Knish.IO server presently ships as a composer module for [Laravel](https://laravel.com/) and [Lumen](https://lumen.laravel.com/), it's very easy to get started running your own Knish.IO node.

## Deployment Instructions
1. Run `git clone https://github.com/WishKnish/KnishIO-Server-Reference-Lumen.git` to create a local copy of the base files.
2. (optional) Switch to the branch you want to use via `bit branch <branch name>`. The branch will determine which matching Knish.IO server and client versions will be included (eg: `master`, `staging`, `develop`, etc.
3. Rename the file `.env.example` to `.env`
4. Edit the `.env` file so that it matches your local environment. Not all fields need to be set immediately - mainly hostnames and database credentials.
5. Pull in vendor dependencies by running `composer install`.
6. Migrate the database structure by running `php artisan migrate`.
7. (Windows only) The vendor package desktopd/php-sha3-streamable cannot create a symbolic link on Windows, so you need to copy the file `SHA3.php` from the package root into `src`, replacing the file that is there.
8. Visit the deployment URL specified in your configuration to verify the deployment is working. You should see something like this:

<div style="text-align:center">
  <img src="https://raw.githubusercontent.com/WishKnish/KnishIO-Server-Reference-Lumen/master/public/assets/images/screenshot-home.png" alt="Screenshot of successful Knish.IO deployment in the browser" />
</div>

## Querying the ledger
Knish.IO uses [GraphQL](https://graphql.org/) as a means of data exchange. GraphQL queries and subscriptions may be used to retrieve ledger state, and GraphQL mutations are used to issue a new transaction.

Typically, all GraphQL messages are sent to a specific endpoint on the node, eg: `https://my.knishio-server.com/graphql`, using HTTP POST or GET. A number of GraphQL client applications exist to help you manually form and test GraphQL messages outside of an application.

### Querying
GraphQL queries are used to retrieve ledger state. Aside from custom meta assets created by users, Knish.IO has a number of built-in data types that can also be queried.

All example queries below can be reduced down to just the fields you need, and follow standard GraphQL syntax.

#### Wallet Bundles
Wallet bundles represent collections of wallets operated on by a single user secret. In other words, a user account.

To retrieve wallet bundle information, issue the following GraphQL query:

```graphql
query WalletBundleQuery($bundleHash: String) {
  WalletBundle(bundleHash: $bundleHash) {
    bundleHash,
    wallets {
      bundleHash
      address,
      tokenSlug,
      token {
        name,
        icon,
      },
      position,
      amount,
      createdAt,
      molecules {
        molecularHash,
      },
      pubkey,
    },
    metas (latest: true){
      key,
      value,
    },
    createdAt,
  },
}
```

#### Wallets
Wallets on Knish.IO are best thought of as disposable keys on a keyring, rather than something static. After every transaction, the wallet used for signing is discarded and may never be used to sign another molecule, for the user's own protection.

To retrieve wallet information, issue the following GraphQL query:

```graphql
query WalletQuery($address: String, $walletBundle: String, $token: String, $unspent: Boolean,) {
  Wallet(address: $address, bundleHash: $walletBundle, token: $token, unspent: $unspent) {
    address,
    bundleHash,
    tokenSlug,
    token {
      slug,
      name,
      fungibility,
      supply,
      decimals,
      icon,
    },
    position,
    amount,
    atoms {
      molecularHash,
      position,
      isotope,
      tokenSlug,
      value,
      createdAt
    },
    createdAt
  }
}
```

#### Tokens
Tokens are digital assets that maintain a balance, and can be used as a counter, a store of incentivized value, and even a means to control the flow of an application. Tokens on Knish.IO are not necessarily cryptocurrencies - they are not traded anywhere, not mined or staked, and not speculative in any way.

To retrieve data regarding a token, issue the following GraphQL query:
```graphql
query TokenQuery($slug: String) {
  Token(slug: $slug) {
    slug,
    name,
    icon,
    fungibility,
    supply,
    decimals,
    amount,
    atoms {
      tokenSlug,
      molecularHash,
      position,
      isotope,
      value,
      createdAt
    },
    wallets {
      address,
      bundleHash,
      position,
      amount,
      createdAt
    },
    metas {
      key,
      value,
      createdAt
    },
    createdAt
  }
}
```
#### MetaTypes
MetaTypes represent custom, user-generated virtual objects. Think of them as class definitions, with instances, properties, and a dApp-specific structure. This is the most common form of querying, as most Knish.IO dApps make extensive use of MetaTypes in order to function.

To retrieve data regarding a MetaType, issue the following GraphQL query:
```graphql
query MetaTypeQuery( $metaType: String, $metaTypes: [String!], $metaId: String, $metaIds: [String!], $key: String, $keys: [String!], $value: String, $values: [String]! ) {
  MetaType( metaType: $metaType, metaTypes: $metaTypes, metaId: $metaId, metaIds: $metaIds, key: $key, keys: $keys, value: $value, values: $values ) {
    metaType,
    createdAt,
    instances {
      metaId,
      metaType,
      createdAt,
      metas {
        molecularHash,
        key,
        value,
        createdAt
      },
    },
  }
}
```

## Mutating the ledger
Knish.IO provides for a single type of GraphQL mutation: the issuance of a new proposed molecule. All mutation logic is based on the contents of the molecule, its atoms, and their respective metadata.

To issue a molecule mutation, first prepare a valid Knish.IO molecule, then execute the following GraphQL code, providing the molecule as the sole parameter:

```graphql
mutation MoleculeMutation($molecule: MoleculeInput!) {
  ProposeMolecule(
    molecule: $molecule,
  ) {
    molecularHash,
    height,
    depth,
    status,
    reason,
    reasonPayload,
    createdAt,
    receivedAt,
    processedAt,
    broadcastedAt
  }
}
```
## Benchmarking
The Lumen reference package provides a number of tools to help you benchmark the performance of your local node.

You can access the benchmark function by running `php artisan molecule:benchmark`. This is a great way to make sure your server installation is working properly.

*Performance Tip:* Knish.IO cryptographic functions will operate much faster with a natively compiled PHP extension for SHA3. See https://github.com/WishKnish/PHP-Ext-Sha3.

## Maintenance
Several node maintenance features are provided for testing and development. They are not meant for a production environment, as local state changes may cause other nodes reject your transactions.

### Cleaning
Sometimes during development it may become necessary to clean the ledger state and eliminate invalid, malformed, and otherwise noncompliant molecules, atoms, metadata, and other artifacts.

To perform cleanup, run `php artisan molecule:clean`.

### Rebonding
If the molecular bonding algorithm changes, or you simply want to re-run it on all molecules on the node, the rebonding procedure will replace all molecular bonds with new ones. This does not affect the molecules' validity, but may impact ledger DAG structure and future trust in a particular transaction.

Depending on the ledger size, this may take anywhere from a few minutes to a few hours, and it **MUST** be completed once started.

To rebond molecules, run `php artisan molecule:rebond`.
