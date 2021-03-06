export const PICTURE_CHANGE_PICTURE_MIN = 'PICTURE_CHANGE_PICTURE_MIN';
export const PICTURE_CHANGE_PICTURE_MIN_MODE = 'PICTURE_CHANGE_PICTURE_MIN_MODE';
export const PICTURE_CHANGE_NAME = 'PICTURE_CHANGE_NAME';
export const PICTURE_CHANGE_DESC = 'PICTURE_CHANGE_DESC';
export const PICTURE_CHANGE_COORD = 'PICTURE_CHANGE_COORD';
export const PICTURE_CHANGE_PICTURE_ONE = 'PICTURE_CHANGE_PICTURE_ONE';
export const PICTURE_REQUEST_DONE = 'PICTURE_REQUEST_DONE';
export const PICTURE_MODAL_ON = 'PICTURE_MODAL_ON';

export const setPictureMin = (newPicture) => {
    return {
        type: PICTURE_CHANGE_PICTURE_MIN,
        payload: newPicture
    }
};

export const setPictureOne = (newPicture) => {
    return {
        type: PICTURE_CHANGE_PICTURE_ONE,
        payload: newPicture
    }
};

export const setPictureMinMode = (newPictureMode) => {
    return {
        type: PICTURE_CHANGE_PICTURE_MIN_MODE,
        payload: newPictureMode
    }
};

export const setPictureName = (namePicture) => {
    return {
        type: PICTURE_CHANGE_NAME,
        payload: namePicture
    }
};

export const setPictureDesc = (descPicture) => {
    return {
        type: PICTURE_CHANGE_DESC,
        payload: descPicture
    }
};

export const setPictureCoord = (coordPicture) => {
    return {
        type: PICTURE_CHANGE_COORD,
        payload: coordPicture
    }
};

export const setPictureRequestDone = (ok) => {
    return {
        type: PICTURE_REQUEST_DONE,
        payload: ok
    }
};

export const setPictureModalOn = (on) => {
    return {
        type: PICTURE_MODAL_ON,
        payload: on
    }
};