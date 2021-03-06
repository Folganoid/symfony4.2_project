<?php

namespace App\Controller;

use App\Entity\Picture;
use App\Entity\PictureComment;
use App\Entity\PictureRating;
use App\Service\AwcS3Service;
use App\Service\ResponseService;
use App\Service\TokenService;
use App\Service\SysService;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Aws\S3\S3Client;


class PictureController extends MainController
{

    public function PictureAction(Request $request, ResponseService $responseService, S3Client $s3, AwcS3Service $awcS3Service)
    {
        $method = $request->getMethod();
        $access = $request->headers->get('Access');

        $doctrine = $this->getDoctrine();
        $manager = $doctrine->getManager();
        $user = (new TokenService())->getUserByAccessToken($access, $manager);
        if (!$user->getLogin() || strlen($user->getLogin()) == 0) return $responseService->buildErrorResponse(404, "Access denied...");

        /**
         * POST
         */
        if ($method == "POST") {

            try {

                $files = $request->files->get('files');
                $names = json_decode($request->get('names'), true);
                $descs = json_decode($request->get('descs'), true);
                $coords = json_decode($request->get('coords'), true);

                for ($i = 0; $i < count($files); $i++) {

                    $handle = fopen($files[$i], "r");
                    $contents = fread($handle, filesize($files[$i]));
                    fclose($handle);

                    $fileName = SysService::getGUID();
                    $fileNameMin = SysService::getGUID();

                    $type = getimagesize($files[$i]->getPathname());
                    if (!$type) return $responseService->buildErrorResponse(404, "File is not image...");

                    try {
                        $link = $awcS3Service->savePicture(md5($user->getLogin()), $fileName, $contents, $s3);
                        $contentsMin = $this->resizeImage($files[$i]->getPathname(), 150, 150);
                        $linkMin = $awcS3Service->savePicture(md5($user->getLogin()), $fileNameMin, $contentsMin, $s3);
                    } catch (\Exception $e) {
                        return $responseService->buildErrorResponse(500, $e->getMessage());
                    }

                    $createAt = \DateTime::createFromFormat('U', time());
                    $createAt->setTimezone(new \DateTimeZone('UTC'));

                    $picture = new Picture();
                    $picture->setUserId($user->getId());
                    $picture->setName($names[$i]);
                    $picture->setDescription($descs[$i]);
                    $picture->setS3link($link);
                    $picture->setS3minlink($linkMin);
                    $picture->setCoord($coords[$i]);
                    $picture->setCreatedAt($createAt);
                    $manager->persist($picture);
                    $manager->flush();
                }

                return $responseService->buildOkResponse([base64_encode($user->getLogin())]);
            } catch (\Exception $e) {
                return $responseService->buildErrorResponse(404, "Pictures upload failed... ERROR:" . $e->getMessage().base64_encode($user->getLogin()));
            }

        /**
         * GET
         */
        } else if ($method == "GET") {

            $repository = $doctrine->getRepository(Picture::class);
            //$pictures = $repository->findBy(['user_id' => $user->getId()]);
            $pictures = $repository->findPictureWithRate($user->getId());

            $pics = [];
            foreach ($pictures as $onePicture) {

                if (isset($pics[$onePicture['id']])) {

                    if (isset($pics[$onePicture['id']]['rateCount'])) {
                        $pics[$onePicture['id']]['rateCount']++;
                        $pics[$onePicture['id']]['rate'] = ($pics[$onePicture['id']]['rate'] + $onePicture['rate']) / 2;
                    }

                } else {

                    $pics[$onePicture['id']] = [
                        "name" => $onePicture['name'],
                        "description" => $onePicture['description'],
                        "coord" => $onePicture['coord'],
                        "s3Link" => $onePicture['s3link'],
                        "body" => $awcS3Service->readPicture($onePicture['s3link'], $s3),
                        "id" => $onePicture['id'],
                    ];

                    if (isset($onePicture["rate"])) {
                        $pics[$onePicture['id']]["rate"] = $onePicture["rate"];
                        $pics[$onePicture['id']]["rateCount"] = 1;
                    }

                }
            }

            return $responseService->buildOkResponse($pics);

        /**
         * DELETE
         */
        } else if ($method == "DELETE") {

            $s3Link = $request->headers->get('S3Link');
            $repository = $doctrine->getRepository(Picture::class);
            $picture = $repository->findOneBy(['s3link' => $s3Link]);

            if (!$picture) return $responseService->buildErrorResponse(404, "Picture not found...");
            if ($user->getId() !== $picture->getUserId()) return $responseService->buildErrorResponse(401, "Access denied...");

            try {
                $deletePic = $awcS3Service->deletePicture($s3Link, $s3);
                $deletePicMin = $awcS3Service->deletePicture($s3Link, $s3);

                if (!$deletePic || !$deletePicMin) throw new \Exception("Can't delete object from AWS S3...");

                $entityManager = $this->getDoctrine()->getManager();

                $repPicRate = $doctrine->getRepository(PictureRating::class);
                $rates = $repPicRate->findBy(['picture_id' => $picture->getId()]);
                foreach ($rates as $oneRate) {
                    $entityManager->remove($oneRate);
                }
                $repPicCom = $doctrine->getRepository(PictureComment::class);
                $comments = $repPicCom->findBy(['picture_id' => $picture->getId()]);
                foreach ($comments as $oneCom) {
                    $entityManager->remove($oneCom);
                }
                $entityManager->remove($picture);
                $entityManager->flush();

            } catch (\Exception $e) {
                return $responseService->buildErrorResponse(404, "ERROR: " . $e->getMessage());
            }

            return $responseService->buildOkResponse(["OK"]);
        }


        return $responseService->buildErrorResponse(500, "Server error...");
    }

    /**
     * get one picture by link
     *
     * @param $bucket
     * @param $s3link
     * @param ResponseService $responseService
     * @param S3Client $s3
     * @param AwcS3Service $awcS3Service
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function PictureOneAction($bucket, $s3link, ResponseService $responseService, S3Client $s3, AwcS3Service $awcS3Service)
    {
        $body = $awcS3Service->readPicture($bucket ."/". $s3link, $s3);
        return $responseService->buildOkResponse(["body" => $body]);
    }

    /**
     * image resize
     *
     * @param string $imagePath
     * @param int $width
     * @param int $height
     * @return string
     * @throws \ImagickException
     */
    function resizeImage(string $imagePath, int $width, int $height) {
        //The blur factor where &gt; 1 is blurry, &lt; 1 is sharp.
        $imagick = new \Imagick($imagePath);
        $imagick->adaptiveResizeImage($width, $height);
        return $imagick->getImageBlob();
    }
}
