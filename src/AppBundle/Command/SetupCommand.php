<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserInterface;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Payment\Model\PaymentMethod;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SetupCommand extends Command
{
    private $productRepository;
    private $productManager;
    private $productFactory;

    private $productAttributeRepository;
    private $productAttributeManager;

    private $localeRepository;
    private $localeFactory;

    private $slugify;

    private $locale;

    private $locales = [
        'ca',
        'fr',
        'en',
        'es',
        'de',
        'it',
        'pl',
    ];

    private $channels = [
        'web' => 'Web',
        'app' => 'App'
    ];

    private $onDemandDeliveryProductNames = [
        'ca' => 'Lliurament a demanda',
        'fr' => 'Livraison à la demande',
        'en' => 'On demand delivery',
        'es' => 'Entrega bajo demanda',
        'de' => 'Lieferung auf Anfrage',
        'it' => 'Consegna su richiesta',
        'pl' => 'Dostawa na żądanie',
    ];

    private $allergenAttributeNames = [
        'ca' => 'Al·lèrgens',
        'fr' => 'Allergènes',
        'en' => 'Allergens',
        'es' => 'Alérgenos',
        'de' => 'Allergene',
        'it' => 'Allergeni',
        'pl' => 'Alergeny',
    ];

    private $restrictedDietsAttributeNames = [
        'ca' => 'Dietes restringides',
        'fr' => 'Régimes restreints',
        'en' => 'Restricted diets',
        'es' => 'Dietas restringidas',
        'de' => 'Eingeschränkte Ernährung',
        'it' => 'Piani ristretti',
        'pl' => 'Ograniczone diety',
    ];

    private $freeDeliveryPromotionNames = [
        'ca' => 'Lliurament gratuït',
        'fr' => 'Livraison offerte',
        'en' => 'Free delivery',
        'es' => 'Entrega gratis',
        'de' => 'Gratisversand',
        'it' => 'Consegna gratuita',
        'pl' => 'Darmowa dostawa',
    ];

    private $currencies = [
        'CAD',
        'EUR',
        'GBP',
        'PLN',
        'USD',
        'SEK',
    ];

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductFactoryInterface $productFactory,
        EntityManagerInterface $productManager,
        RepositoryInterface $productAttributeRepository,
        EntityManagerInterface $productAttributeManager,
        RepositoryInterface $localeRepository,
        FactoryInterface $localeFactory,
        ChannelRepositoryInterface $channelRepository,
        ChannelFactoryInterface $channelFactory,
        RepositoryInterface $currencyRepository,
        FactoryInterface $currencyFactory,
        PromotionRepositoryInterface $promotionRepository,
        FactoryInterface $promotionFactory,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        FactoryInterface $taxCategoryFactory,
        ManagerRegistry $doctrine,
        SlugifyInterface $slugify,
        TranslatorInterface $translator,
        string $locale)
    {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productManager = $productManager;

        $this->productAttributeRepository = $productAttributeRepository;
        $this->productAttributeManager = $productAttributeManager;

        $this->localeRepository = $localeRepository;
        $this->localeFactory = $localeFactory;

        $this->channelRepository = $channelRepository;
        $this->channelFactory = $channelFactory;

        $this->currencyRepository = $currencyRepository;
        $this->currencyFactory = $currencyFactory;

        $this->promotionRepository = $promotionRepository;
        $this->promotionFactory = $promotionFactory;

        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryFactory = $taxCategoryFactory;

        $this->paymentMethodRepository =
            $doctrine->getRepository(PaymentMethod::class);

        $this->slugify = $slugify;

        $this->translator = $translator;

        $this->locale = $locale;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:setup')
            ->setDescription('Setups some basic stuff.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Setting up CoopCycle…</info>');

        $output->writeln('<info>Checking Sylius locales are present…</info>');
        foreach ($this->locales as $locale) {
            $this->createSyliusLocale($locale, $output);
        }

        $output->writeln('<info>Checking Sylius channels are present…</info>');
        foreach ($this->channels as $channelCode => $channelName) {
            $this->createSyliusChannel($channelCode, $channelName, $output);
        }

        $output->writeln('<info>Checking Sylius currencies are present…</info>');
        foreach ($this->currencies as $currencyCode) {
            $this->createSyliusCurrency($currencyCode, $output);
        }

        $output->writeln('<info>Checking Sylius payment methods are present…</info>');
        $this->createSyliusPaymentMethods($output);

        $output->writeln('<info>Checking « on demand delivery » product is present…</info>');
        $this->createOnDemandDeliveryProduct($output);

        $output->writeln('<info>Checking Sylius product attributes are present…</info>');
        $this->createAllergensAttributes($output);
        $this->createRestrictedDietsAttributes($output);

        $output->writeln('<info>Checking Sylius free delivery promotion is present…</info>');
        $this->createFreeDeliveryPromotion($output);

        $output->writeln('<info>Checking Sylius taxes are present…</info>');
        $this->createSyliusTaxes($output);

        return 0;
    }

    private function createSyliusLocale($code, OutputInterface $output)
    {
        $locale = $this->localeRepository->findOneByCode($code);

        if (null !== $locale) {
            $output->writeln(sprintf('Sylius locale "%s" already exists', $code));
            return;
        }

        $locale = $this->localeFactory->createNew();
        $locale->setCode($code);

        $this->localeRepository->add($locale);

        $output->writeln(sprintf('Sylius locale "%s" created', $code));
    }

    private function createSyliusChannel($code, $name, OutputInterface $output)
    {
        $channel = $this->channelRepository->findOneByCode($code);

        if (null !== $channel) {
            $output->writeln(sprintf('Sylius channel "%s" already exists', $code));
            return;
        }

        $channel = $this->channelFactory->createNamed($name);
        $channel->setCode($code);

        $this->channelRepository->add($channel);

        $output->writeln(sprintf('Sylius channel "%s" created', $code));
    }

    private function createSyliusCurrency($code, OutputInterface $output)
    {
        $currency = $this->currencyRepository->findOneByCode($code);

        if (null !== $currency) {
            $output->writeln(sprintf('Sylius currency "%s" already exists', $code));
            return;
        }

        $currency = $this->currencyFactory->createNew();
        $currency->setCode($code);

        $this->currencyRepository->add($currency);

        $output->writeln(sprintf('Sylius currency "%s" created', $code));
    }

    private function createSyliusPaymentMethods(OutputInterface $output)
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByCode('STRIPE');

        if (null === $paymentMethod) {

            $paymentMethod = new PaymentMethod();

            $paymentMethod->setCode('STRIPE');
            $paymentMethod->enable();

            foreach ($this->locales as $locale) {

                $paymentMethod->setFallbackLocale($locale);
                $translation = $paymentMethod->getTranslation($locale);

                $translation->setName('Stripe');
            }

            $this->paymentMethodRepository->add($paymentMethod);

            $output->writeln('Creating payment method « Stripe »');
        } else {
            $output->writeln('Payment method « Stripe » already exists');
        }
    }

    private function createOnDemandDeliveryProduct(OutputInterface $output)
    {
        $product = $this->productRepository->findOneByCode('CPCCL-ODDLVR');

        if (null === $product) {

            $product = $this->productFactory->createNew();
            $product->setCode('CPCCL-ODDLVR');
            $product->setEnabled(true);

            $this->productRepository->add($product);
            $output->writeln('Creating product « on demand delivery »');

        } else {
            $output->writeln('Product « on demand delivery » already exists');
        }

        $output->writeln('Verifying translations for product « on demand delivery »');

        foreach ($this->locales as $locale) {

            $name = $this->onDemandDeliveryProductNames[$locale];

            $product->setFallbackLocale($locale);
            $translation = $product->getTranslation($locale);

            $translation->setName($name);
            $translation->setSlug($this->slugify->slugify($name));
        }

        $this->productManager->flush();
    }

    private function createAllergensAttributes(OutputInterface $output)
    {
        $attribute = $this->productAttributeRepository->findOneByCode('ALLERGENS');

        if (null === $attribute) {

            $attribute = new ProductAttribute();
            $attribute->setCode('ALLERGENS');
            $attribute->setType(TextAttributeType::TYPE);
            $attribute->setStorageType(AttributeValueInterface::STORAGE_JSON);

            $this->productAttributeRepository->add($attribute);
            $output->writeln('Creating attribute « ALLERGENS »');

        } else {
            $output->writeln('Attribute « ALLERGENS » already exists');
        }

        $output->writeln('Verifying translations for attribute « ALLERGENS »');

        foreach ($this->locales as $locale) {

            $attribute->setFallbackLocale($locale);
            $translation = $attribute->getTranslation($locale);

            $translation->setName($this->allergenAttributeNames[$locale]);
        }

        $this->productAttributeManager->flush();
    }

    private function createRestrictedDietsAttributes(OutputInterface $output)
    {
        $attribute = $this->productAttributeRepository->findOneByCode('RESTRICTED_DIETS');

        if (null === $attribute) {

            $attribute = new ProductAttribute();
            $attribute->setCode('RESTRICTED_DIETS');
            $attribute->setType(TextAttributeType::TYPE);
            $attribute->setStorageType(AttributeValueInterface::STORAGE_JSON);

            $this->productAttributeRepository->add($attribute);
            $output->writeln('Creating attribute « RESTRICTED_DIETS »');

        } else {
            $output->writeln('Attribute « RESTRICTED_DIETS » already exists');
        }

        $output->writeln('Verifying translations for attribute « RESTRICTED_DIETS »');

        foreach ($this->locales as $locale) {

            $attribute->setFallbackLocale($locale);
            $translation = $attribute->getTranslation($locale);

            $translation->setName($this->restrictedDietsAttributeNames[$locale]);
        }

        $this->productAttributeManager->flush();
    }

    private function createFreeDeliveryPromotion(OutputInterface $output)
    {
        $promotion = $this->promotionRepository->findOneByCode('FREE_DELIVERY');

        if (null === $promotion) {

            $promotion = $this->promotionFactory->createNew();
            $promotion->setName($this->freeDeliveryPromotionNames[$this->locale]);
            $promotion->setCouponBased(true);
            $promotion->setCode('FREE_DELIVERY');
            $promotion->setPriority(1);

            $promotionAction = new PromotionAction();
            $promotionAction->setType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
            $promotionAction->setConfiguration(['percentage' => 1.0]);

            $promotion->addAction($promotionAction);

            $this->promotionRepository->add($promotion);
            $output->writeln('Creating promotion « FREE_DELIVERY »');

        } else {
            $output->writeln('Promotion « FREE_DELIVERY » already exists');
        }
    }

    private function createSyliusTaxes(OutputInterface $output)
    {


        $expectedTaxCategories = [
            [
                'name' => 'tax_category.food',
                'code' => 'FOOD',
            ],
            [
                'name' => 'tax_category.drink',
                'code' => 'DRINK',
            ],
            [
                'name' => 'tax_category.alcoholic_drink',
                'code' => 'ALCOHOLIC_DRINK',
            ],
            [
                'name' => 'tax_category.service',
                'code' => 'SERVICE',
            ],
        ];

        foreach ($expectedTaxCategories as $data) {

            $taxCategory = $this->taxCategoryRepository->findOneByCode($data['code']);

            if (null === $taxCategory) {
                $output->writeln(sprintf('Creating tax category « %s »', $data['code']));
                $taxCategory = $this->taxCategoryFactory->createNew();
                $taxCategory->setCode($data['code']);
                $taxCategory->setName($data['code']);
            }

            // var_dump($taxCategory->getName());
            # code...
        }
    }
}
