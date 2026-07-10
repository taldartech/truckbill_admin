<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Sales Partner onboarding',
        description: 'Complete your profile and create your login credentials',
    },
});

const props = defineProps<{
    token: string;
    email: string;
}>();

const form = useForm({
    legal_name: '',
    mobile: '',
    working_location: '',
    current_company_name: '',
    pan_number: '',
    aadhar_number: '',
    bank_name: '',
    bank_account_number: '',
    ifsc_code: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(`/partner-onboarding/${props.token}`);
}
</script>

<template>
    <Head title="Sales Partner onboarding" />

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input id="email" :model-value="email" type="email" readonly class="bg-muted" />
        </div>

        <div class="grid gap-2">
            <Label for="legal_name">Name (as on PAN / Aadhaar) *</Label>
            <Input id="legal_name" v-model="form.legal_name" required />
            <InputError :message="form.errors.legal_name" />
        </div>

        <div class="grid gap-2">
            <Label for="mobile">Mobile number *</Label>
            <Input id="mobile" v-model="form.mobile" type="tel" required maxlength="10" placeholder="10-digit mobile" />
            <InputError :message="form.errors.mobile" />
        </div>

        <div class="grid gap-2">
            <Label for="working_location">Working location</Label>
            <Input id="working_location" v-model="form.working_location" />
            <InputError :message="form.errors.working_location" />
        </div>

        <div class="grid gap-2">
            <Label for="current_company_name">Current company name</Label>
            <Input id="current_company_name" v-model="form.current_company_name" />
            <InputError :message="form.errors.current_company_name" />
        </div>

        <div class="grid gap-2">
            <Label for="pan_number">PAN card number</Label>
            <Input id="pan_number" v-model="form.pan_number" maxlength="10" class="uppercase" placeholder="ABCDE1234F" />
            <InputError :message="form.errors.pan_number" />
        </div>

        <div class="grid gap-2">
            <Label for="aadhar_number">Aadhaar card number</Label>
            <Input id="aadhar_number" v-model="form.aadhar_number" maxlength="12" placeholder="12-digit Aadhaar" />
            <InputError :message="form.errors.aadhar_number" />
        </div>

        <div class="grid gap-2">
            <Label for="bank_name">Bank name</Label>
            <Input id="bank_name" v-model="form.bank_name" />
            <InputError :message="form.errors.bank_name" />
        </div>

        <div class="grid gap-2">
            <Label for="bank_account_number">Bank account number</Label>
            <Input id="bank_account_number" v-model="form.bank_account_number" />
            <InputError :message="form.errors.bank_account_number" />
        </div>

        <div class="grid gap-2">
            <Label for="ifsc_code">IFSC code</Label>
            <Input id="ifsc_code" v-model="form.ifsc_code" maxlength="11" class="uppercase" placeholder="ABCD0123456" />
            <InputError :message="form.errors.ifsc_code" />
        </div>

        <div class="border-t border-border pt-4">
            <h2 class="mb-4 text-sm font-semibold">Create your password</h2>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="password">Password *</Label>
                    <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password *</Label>
                    <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" />
                </div>
            </div>
        </div>

        <Button type="submit" class="w-full" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            Complete onboarding
        </Button>
    </form>
</template>
