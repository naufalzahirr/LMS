export type ResultFreshnessController = {
    onMount: () => void;
    onPageShow: (persisted: boolean) => void;
};

export function createResultFreshnessController(
    revalidate: () => void,
): ResultFreshnessController {
    return {
        onMount() {
            revalidate();
        },
        onPageShow(persisted) {
            if (persisted) {
                revalidate();
            }
        },
    };
}
