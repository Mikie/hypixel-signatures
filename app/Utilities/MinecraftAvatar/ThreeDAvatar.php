<?php
/**
 * Copyright (c) 2020 Max Korlaar
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions, a visible attribution to the original author(s)
 *   of the software available to the public, and the following disclaimer
 *   in the documentation and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

    namespace App\Utilities\MinecraftAvatar;

    use Cache;
    use Exception;
    use Log;
    use RuntimeException;

    require_once(__DIR__ . '/MCavatar.php');
    require_once(__DIR__ . '/GifCreator.php');
    require_once(__DIR__ . '/3d.class.php');

    /**
     * Class moreMCavatar
     *
     * @author  Max Korlaar
     * @license MIT
     */
    class ThreeDAvatar extends MCavatar {
        public $images;
        public $username;
        public $headOnly;
        public $helmet;
        public $layers;
        public $speed;
        public $frames;
        public $filepath;
        public $invert;

        /** @var render3DPlayer $playerRender */
        private $playerRender = null;

        /**
         * @param        $username
         * @param int    $size
         * @param int    $speed
         * @param int    $rotation
         * @param bool   $headOnly
         * @param bool   $helmet
         * @param bool   $layers
         * @param string $return
         *
         * @return bool|resource|string
         * @throws Exception
         */
        public function getRotatingSkinFromCache($username, $size = 2, $speed = 3, $rotation = 5, $headOnly = false, $helmet = true, $layers = false, $return = 'binary') {

            if ($layers) {
                $l = '-withlayers';
            } else {
                $l = '';
            }

            if ($speed !== 3 || $rotation !== 5) {
                $imagepath = $this->imagepath . 'rotate_gif/' . strtolower($username) . "-{$size}x-{$speed}s-{$rotation}fms{$l}.gif";
            } else {
                $imagepath = $this->imagepath . 'rotate_gif/' . strtolower($username) . "-{$size}x{$l}.gif";
            }
            $this->filepath = $imagepath;

            if (file_exists($imagepath)) {
                if (filemtime($imagepath) < strtotime('-1 week')) {
                    $this->cacheInfo = '3d full skin expired, redownloading';

                    if ($return === 'binary') {
                        return $this->getRotatingSkin($username, $size, $speed, $rotation, $headOnly, $helmet, $layers, 'save-binary');
                    }
                    return $this->getRotatingSkin($username, $size, $speed, $rotation, $headOnly, $helmet, $layers, $return);

                }

                $this->cacheInfo = '3d full skin image exists and OK';
                if ($return === 'binary') {
                    return file_get_contents($imagepath);
                }
                if ($return === 'url' || $return === 'resource') {
                    if ($return === 'resource') {
                        return imagecreatefromgif($imagepath);
                    }

                    return $imagepath;
                }
                return $imagepath;
            }

            $this->cacheInfo = '3d full skin image not yet downloaded';
            if ($return === 'binary') {
                return $this->getRotatingSkin($username, $size, $speed, $rotation, $headOnly, $helmet, $layers, 'save-binary');
            }
            return $this->getRotatingSkin($username, $size, $speed, $rotation, $headOnly, $helmet, $return);
        }

        /**
         * @param        $username
         * @param int    $size
         * @param int    $speed
         * @param int    $frames
         * @param bool   $headOnly
         * @param bool   $helmet
         * @param bool   $layers
         * @param string $return
         *
         * @return bool|resource|string
         * @throws Exception
         * @internal param int $rotation
         */
        public function getRotatingSkin($username, $size = 2, $speed = 3, $frames = 5, $headOnly = false, $helmet = true, $layers = false, $return = 'binary') {
            $this->images   = [];
            $this->username = $username;
            $this->size     = $size;
            $this->headOnly = $headOnly;
            $this->helmet   = $helmet;
            $this->layers   = $layers;
            $this->speed    = $speed;
            $this->frames   = $frames;
            /**
             * @param $angle
             */
            $rotation = function ($angle) {
                if ($this->invert) {
                    $angle *= -1;
                }
                $player         = new render3DPlayer($this->username, '0', $angle, '0', '0', '0', '0', '0', "{$this->helmet}", "{$this->headOnly}", 'png', $this->size, $this->layers);
                $this->images[] = $player->get3DRender();
            };

            if ($frames < 0) {
                $frames       *= -1;
                $this->invert = true;
            } else {
                $this->invert = false;
            }

            $circle = range(0, 360, $frames);
            array_map($rotation, $circle);
            $durations = [];
            for ($i = 0; $i < 360 / $frames + 1; $i++) {
                $durations[] = $speed;
            }
            $gc = new GifCreator();
            $gc->create($this->images, $durations, 0);
            $gifBinary = $gc->getGif();

            foreach ($this->images as $img) {
                imagedestroy($img);
            }

            if ($return === 'binary') {
                return $gifBinary;
            }

            if ($return === 'url' || $return === 'resource' || $return === 'save-binary') {
                if ($layers) {
                    $l = '-withlayers';
                } else {
                    $l = '';
                }
                if ($this->invert) {
                    $frames *= -1;
                }
                if ($speed !== 3 || $frames !== 5) {
                    $imagepath = $this->imagepath . 'rotate_gif/' . strtolower($username) . "-{$size}x-{$speed}s-{$frames}fms{$l}.gif";
                } else {
                    $imagepath = $this->imagepath . 'rotate_gif/' . strtolower($username) . "-{$size}x{$l}.gif";
                }

                if (!file_exists($this->imagepath . 'rotate_gif/') && !mkdir($concurrentDirectory = $this->imagepath . 'rotate_gif/', 0777, true) && !is_dir($concurrentDirectory)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
                @file_put_contents($imagepath, $gifBinary);

                if ($return === 'resource') {
                    return imagecreatefromgif($imagepath);
                }

                if ($return === 'save-binary') {
                    return $gifBinary;
                }

                return $imagepath;
            }
            return false;
        }

        /**
         * @param      $username
         * @param int  $size
         * @param int  $angle
         * @param bool $headOnly
         * @param bool $helmet
         * @param bool $layers
         *
         * @return bool|resource
         */
        public function getThreeDSkinFromCache($username, $size = 2, $angle = 0, $headOnly = false, $helmet = true, $layers = false) {
            if ($headOnly) {
                $h = '-head';
            } else {
                $h = '';
            }
            if (!$helmet) {
                $nh = '-nohelm';
            } else {
                $nh = '';
            }
            if ($layers) {
                $l = '-withlayers';
            } else {
                $l = '';
            }

            $imagepath = $this->imagepath . '3d/' . strtolower($username) . "-{$size}x-{$angle}{$h}{$nh}{$l}.png";

            return Cache::lock('minecraft.avatar.' . $imagepath)->block(5, function () use ($layers, $helmet, $headOnly, $angle, $size, $username, $imagepath) {
                if (!file_exists($this->imagepath . '3d/') && !mkdir($concurrentDirectory = $this->imagepath . '3d/', 0777, true) && !is_dir($concurrentDirectory)) {
                    throw new RuntimeException("Directory \"{$concurrentDirectory}\" was not created");
                }

                if (file_exists($imagepath)) {
                    if (filemtime($imagepath) < strtotime('-3 days')) {
                        Log::debug('3d skin expired, regenerating', ['path' => $imagepath]);

                        $image = $this->getThreeDSkin($username, $size, $angle, $headOnly, $helmet, $layers);

                        if ($this->playerRender->fetchError === null) {
                            imagepng($image, $imagepath);
                        } // Only cache the image if it's fetched successfully.

                        return $image;
                    }

                    Log::debug('3d skin image exists and OK', ['path' => $imagepath]);
                    return imagecreatefrompng($imagepath);
                }

                Log::debug('3d skin image not yet generated', ['path' => $imagepath]);
                $image = $this->getThreeDSkin($username, $size, $angle, $headOnly, $helmet, $layers);

                if ($this->playerRender->fetchError === null) {
                    imagepng($image, $imagepath);
                } // Only cache the image if it's fetched successfully.

                return $image;
            });
        }

        /**
         * @param      $username
         * @param int  $size
         * @param int  $angle
         * @param bool $headOnly
         * @param bool $helmet
         * @param bool $layers
         *
         * @return resource|string
         */
        public function getThreeDSkin($username, $size = 2, $angle = 0, $headOnly = false, $helmet = true, $layers = false) {
            $this->username     = $username;
            $this->size         = $size;
            $this->headOnly     = $headOnly;
            $this->helmet       = $helmet;
            $this->layers       = $layers;
            $this->playerRender = new render3DPlayer($this->username, '0', $angle, '0', '0', '0', '0', '0', $this->helmet, $this->headOnly, 'png', $this->size, $this->layers, true);
            return $this->playerRender->get3DRender();
        }

    }
