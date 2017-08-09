---
layout: default
---

# About Ivory
{:.no_toc}

* TOC
{:toc}


Ivory is an **open-source PHP database access layer** specialized for PostgreSQL. Compared to the many database
abstraction layers, which are able to work with multiple database systems, the purpose of Ivory is to **focus only to
PostgreSQL**, and to **do it right**. More on the values below.

Ivory aspires to offer a **rich feature set**. Just as PostgreSQL is not a lightweight database system, Ivory is rather
a robust database layer, too. All major features of PostgreSQL are either supported already, or planned for a future
release. See the [Features](features.md).

Officialy supported are PHP ≥ 7.1 and PostgreSQL ≥ 9.4, although it *might* work on older PostgreSQL versions.



## Values

Ivory has been built with several key values in mind. Discussing them will, hopefully, help with deciding whether Ivory
is the choice for a particular project, or other solutions should rather be given a try.

### Specialized for PostgreSQL

The common disadvantage in supporting multiple database systems is that either the vendor-specific features are
omitted, or their usage is more complicated in order to abstract out DBMS differences.

Ivory is made to **only support PostgreSQL**, inviting the user to **leverage the specific features** without extra
hurdles.


### Provide an Access Layer

Ivory is a database *access* layer only, i.e., it does not implement active records or any other abstraction over the
database. Especially, it is **not an <abbr title="Object-Relational Mapper">ORM</abbr>**. Instead, Ivory invites the
user to specify the exact statements being executed.

Higher-level layers might be implemented as standalone projects on top of Ivory.


### SQL as the Primary Language

Many database layers offer an object-oriented query builder, which introduces special objects and methods composing the
desired queries, hiding the actual SQL behind. There are two typical approaches:
* either the API is so generic it just mechanically translates method calls to SQL keywords, without actually
  understanding them, or
* specialized objects and methods are used to make certain, well-defined queries.

The former inherently lacks type safety, largely reducing the typical advantage of the OOP paradigm. The latter, on the
other hand, limits the expression strength. Moreover, both require the user to learn a yet another way to specify what
the database should retrieve or perform. On top of that, the different language even becomes an obstacle when one
refactors the code and wants to move a statement definition from the application level to a database view or function,
or vice versa.

Ivory respects the elephants and **prefers the SQL itself** as it is a standard, long-term proven language specifically
designed for relational data retrieval. While offering specialized methods to ease transaction and session control, for
data queries, Ivory provides mere helpers for typing SQL.


### Type Safety

**Type safety is honored** to capture errors early. For example, one cannot mix dates with date/time values unless an
explicit conversion method is used.

Yet, different situations call for measures of different strictness. The API defines overloaded methods so that the user
is not forced too much. E.g., wherever taking a callable piece of code as a method argument, both `Closure` and objects
implementing a well-defined interface are accepted, the latter leading to safer code at the cost of more boilerplate.


### Relations as First-Class Citizens

Ivory mixes in a little bit of science. Data sets are called *relations*, and are **treated as just complex values**.
Complex relations may be composed on top of each other, using a special placeholder which serializes a relation into a
table expression.



## License

Ivory is released under the [BSD 3-clause license](https://github.com/ondrej-bouda/ivory/blob/master/LICENSE). Thus, it
may especially be used in commercial and closed-source software, provided the copyright notice is retained. See the
LICENSE file shipped with Ivory for details.


