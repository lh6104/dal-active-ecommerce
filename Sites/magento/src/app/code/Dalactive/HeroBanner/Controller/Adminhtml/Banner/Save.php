<?php

namespace Dalactive\HeroBanner\Controller\Adminhtml\Banner;

use Dalactive\HeroBanner\Model\BannerFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Filesystem;
use Magento\Framework\File\UploaderFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Dalactive_HeroBanner::banners';
    private const MAX_UPLOAD_SIZE = 20971520;

    private BannerFactory $bannerFactory;
    private Filesystem $filesystem;
    private UploaderFactory $uploaderFactory;

    public function __construct(
        Context $context,
        BannerFactory $bannerFactory,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory
    ) {
        $this->bannerFactory = $bannerFactory;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $result = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $result->setPath('*/*/');
        }

        try {
            $banner = $this->bannerFactory->create();
            if (!empty($data['slide_id'])) {
                $banner->load((int) $data['slide_id']);
            }

            $mediaPath = (string) ($data['media_path'] ?? $banner->getData('media_path'));
            if (isset($_FILES['media_file']) && !empty($_FILES['media_file']['name'])) {
                $mediaPath = $this->uploadMedia();
            }

            if (!$mediaPath) {
                throw new \RuntimeException((string) __('Please upload a banner image or MP4 video.'));
            }

            $title = (string) ($data['headline'] ?? '');
            $extension = strtolower((string) pathinfo($mediaPath, PATHINFO_EXTENSION));
            $banner->addData([
                'name' => $title,
                'media_type' => $extension === 'mp4' ? 'video' : 'image',
                'media_path' => $mediaPath,
                'headline' => $title,
                'subtitle' => (string) ($data['subtitle'] ?? ''),
                'button1_text' => (string) ($data['button1_text'] ?? ''),
                'button1_url' => (string) ($data['button1_url'] ?? ''),
                'button2_text' => '',
                'button2_url' => '',
                'text_color' => '#ffffff',
                'button_style' => 'light',
                'timeout_ms' => $this->normalizeTimeout($data['timeout_ms'] ?? null),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'status' => (int) ($data['status'] ?? 0),
            ]);
            $banner->save();
            $this->messageManager->addSuccessMessage(__('Hero banner was saved.'));
            return $result->setPath('*/*/edit', ['slide_id' => $banner->getId()]);
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Unable to save hero banner: %1', $exception->getMessage()));
            return $result->setPath('*/*/edit', ['slide_id' => (int) ($data['slide_id'] ?? 0)]);
        }
    }

    private function uploadMedia(): string
    {
        if (isset($_FILES['media_file']['size']) && (int) $_FILES['media_file']['size'] > self::MAX_UPLOAD_SIZE) {
            throw new \RuntimeException((string) __('Hero banner media must be 20MB or smaller.'));
        }

        $uploader = $this->uploaderFactory->create(['fileId' => 'media_file']);
        $uploader->setAllowedExtensions(['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'mp4']);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $target = $mediaDirectory->getAbsolutePath('dalactive/herobanner');
        $result = $uploader->save($target);

        return 'dalactive/herobanner/' . ltrim((string) $result['file'], '/');
    }

    private function normalizeTimeout($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(1000, (int) $value);
    }
}
