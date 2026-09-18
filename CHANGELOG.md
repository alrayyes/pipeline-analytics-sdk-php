# Changelog

## [0.5.1](https://github.com/alrayyes/pipeline-analytics-sdk-php/compare/v0.5.0...v0.5.1) (2026-09-18)


### Bug Fixes

* **test:** use Pest's native mutation testing instead of Infection ([ccb41a1](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/ccb41a13c79443e333a6274cd34d9395d57d051b))
* **test:** use Pest's native mutation testing instead of Infection ([c818546](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/c8185465c8b664fd866d34e2555a1012c770912e)), closes [#37](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/37)

## [0.5.0](https://github.com/alrayyes/pipeline-analytics-sdk-php/compare/v0.4.0...v0.5.0) (2026-09-18)


### Features

* **test:** contract-test the client against a Prism mock ([#31](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/31)) ([4b4ae02](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/4b4ae0264b0e3f633abe5c8ed406c5efb73ae3a6)), closes [#23](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/23)

## [0.4.0](https://github.com/alrayyes/pipeline-analytics-sdk-php/compare/v0.3.0...v0.4.0) (2026-09-18)


### Features

* **lint:** wire PHP Mess Detector into CI and pre-push ([#28](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/28)) ([821c685](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/821c68593a4c3c4eae84df396fd879baedb21426))

## [0.3.0](https://github.com/alrayyes/pipeline-analytics-sdk-php/compare/v0.2.0...v0.3.0) (2026-09-18)


### Features

* **test:** wire Infection mutation testing on the hand-written layer ([#29](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/29)) ([79aad90](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/79aad90bd257a0697065d3335c253ae89b9dde63))

## [0.2.0](https://github.com/alrayyes/pipeline-analytics-sdk-php/compare/v0.1.2...v0.2.0) (2026-09-18)


### Features

* **security:** add Bearer SAST scan to CI and pre-push ([2f59f22](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/2f59f2278e8c0ef15c762eb5d9a89b97f0c4eadf))
* **security:** add Bearer SAST scan to CI and pre-push ([d840bc7](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/d840bc71f7ce24460beeb012ccc91d64b57d33af)), closes [#22](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/22)


### Bug Fixes

* **ci:** run the bearer container job as root ([f3da86c](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/f3da86c6b33ab0e35a8e858673a1752b9d0bd8da))

## [0.1.2](https://github.com/alrayyes/pipeline-analytics-sdk-php/compare/v0.1.1...v0.1.2) (2026-09-18)


### Bug Fixes

* **ci:** classify spec regenerations as fix/fix! via oasdiff ([#19](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/19)) ([8a36fc7](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/8a36fc7018d7ad41a11d584fe22a7fde2196c383))
* **ci:** isolate Codecov upload into its own non-required job ([#16](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/16)) ([499cb78](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/499cb78892febaacc1ba3e9d132d7537eae1dbde)), closes [#15](https://github.com/alrayyes/pipeline-analytics-sdk-php/issues/15)
* **ci:** quote the autorelease if: condition in release-auto-merge.yml ([efa837e](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/efa837e9c5cae5651f42b221de868c35de98933d))


### Miscellaneous Chores

* **composer:** wrap composer normalize in fix/lint scripts ([cbb81e8](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/cbb81e8dbd9e292a50e9f29705cc9a4d041089c1))

## [0.1.1](https://github.com/alrayyes/pipeline-analytics-sdk-php/compare/v0.1.0...v0.1.1) (2026-09-18)


### Miscellaneous Chores

* **git:** collapse composer.lock/bun.lock diffs, add base gitattributes ([678f2e7](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/678f2e7b047a41c657eb28df7207263d9a42df9a))
* **git:** collapse composer.lock/bun.lock diffs, add base gitattributes ([0bf9e0d](https://github.com/alrayyes/pipeline-analytics-sdk-php/commit/0bf9e0d7f8e48db9867ddaf6e5c1ba2cec28e619))
